"""Run the Motorola SB6141 data input script against canned modem pages."""
import contextlib
import io
from pathlib import Path
import runpy
import sys
import types
import unittest
from unittest import mock
import xml.etree.ElementTree as ElementTree

ROOT = Path(__file__).resolve().parents[2]
SCRIPT = ROOT / 'scripts' / 'motorola_surfboard_SB6141.py'


def table(category, channels, rows):
    head = ''.join(f'<td>{channel}</td>' for channel in channels)
    body = ''.join(
        '<tr><td>{}</td>{}</tr>'.format(label, ''.join(f'<td>{value}</td>' for value in values))
        for label, values in rows
    )
    return (f'<table><tbody><tr><th><font>{category}</font></th></tr>'
            f'<tr><td>Channel ID</td>{head}</tr>{body}</tbody></table>')


def page(*tables):
    return '<html><body>{}</body></html>'.format(''.join(tables))


DOWN = [str(channel) for channel in range(1, 9)]
UP = ['5', '6', '7', '8']
MODEM_PAGE = page(
    table('Downstream', DOWN, [
        ('Signal to Noise Ratio', [f'3{n} dB' for n in DOWN]),
        ('Power Level', [f'-{n} dBmV' for n in DOWN]),
    ]),
    table('Upstream', UP, [('Power Level', [f'4{n} dBmV' for n in UP])]),
    table('Signal Stats (Codewords)', DOWN, [
        ('Total Unerrored Codewords', [f'100{n}' for n in DOWN]),
        ('Total Correctable Codewords', [f'20{n}' for n in DOWN]),
        ('Total Uncorrectable Codewords', [f'3{n}' for n in DOWN]),
    ]),
)


class RequestException(Exception):
    pass


class Response:
    """Streamed response double matching the parts of requests the script uses."""

    def __init__(self, text, error=None, status_code=200, chunks=None):
        self.body = text.encode()
        self.error = error
        self.status_code = status_code
        self.chunks = chunks
        self.closed = False

    def raise_for_status(self):
        if self.error:
            raise RequestException(self.error)

    def iter_content(self, chunk_size):
        if self.chunks is not None:
            yield from self.chunks
            return
        for start in range(0, len(self.body), chunk_size):
            yield self.body[start:start + chunk_size]

    def close(self):
        self.closed = True


class TreeShim:
    """Enough of lxml.html for the script when lxml is not installed."""

    def __init__(self, root):
        self.root = root

    def xpath(self, query):
        assert query == '//table/tbody'
        return [body for node in self.root.iter('table') for body in node.findall('tbody')]


def html_module():
    try:
        from lxml import html  # pylint: disable=import-outside-toplevel
        return html
    except ImportError:
        return types.SimpleNamespace(fromstring=lambda text: TreeShim(ElementTree.fromstring(text)))


class MotorolaSurfboardScriptTest(unittest.TestCase):
    def run_script(self, args, text=MODEM_PAGE, error=None, response=None):
        self.response = response or Response(text, error)
        requests = types.ModuleType('requests')
        requests.RequestException = RequestException
        requests.get = mock.Mock(return_value=self.response)
        lxml = types.ModuleType('lxml')
        lxml.html = html_module()
        stdout, stderr = io.StringIO(), io.StringIO()
        modules = {'requests': requests, 'lxml': lxml, 'lxml.html': lxml.html}
        code = 0
        with mock.patch.dict(sys.modules, modules), \
                mock.patch.object(sys, 'argv', [str(SCRIPT), *args]), \
                contextlib.redirect_stdout(stdout), contextlib.redirect_stderr(stderr):
            try:
                runpy.run_path(str(SCRIPT), run_name='__main__')
            except SystemExit as exc:
                code = exc.code
        return code, stdout.getvalue(), stderr.getvalue(), requests.get

    def test_downstream_output_keeps_field_names_and_order(self):
        code, out, err, get = self.run_script(['Downstream', 'modem.example'])
        self.assertEqual((code, err), (0, ''))
        get.assert_called_once_with('http://modem.example/cmSignalData.htm', timeout=10,
                                    allow_redirects=False, stream=True)
        self.assertTrue(self.response.closed)
        expected = [f'DownstreamPower{n}:-{n}' for n in DOWN]
        expected += [f'DownstreamNoiseRatio{n}:3{n}' for n in DOWN]
        self.assertEqual(out, ' '.join(expected) + '\n')

    def test_host_with_port_and_bracketed_ipv6_is_accepted(self):
        for host in ('192.0.2.1:8080', '[2001:db8::1]:80', 'modem-1.example'):
            with self.subTest(host=host):
                code, _, _, get = self.run_script(['Upstream', host])
                self.assertEqual(code, 0)
                self.assertEqual(get.call_args.args[0], f'http://{host}/cmSignalData.htm')

    def test_host_that_is_not_a_bare_name_or_address_is_rejected(self):
        for host in ('-modem', 'user@modem', 'modem/x', 'modem?x', 'modem#x', 'mo dem', 'modem\n', ''):
            with self.subTest(host=host):
                code, out, err, get = self.run_script(['Downstream', host])
                self.assertEqual((code, out), (1, ''))
                self.assertIn('invalid modem hostname or IP', err)
                get.assert_not_called()

    def test_redirect_is_not_followed(self):
        code, out, err, _ = self.run_script(['Downstream', 'modem.example'],
                                            response=Response('', status_code=302))
        self.assertEqual((code, out), (1, ''))
        self.assertIn('unexpected HTTP status 302', err)

    def test_oversized_response_is_abandoned(self):
        chunks = [b'x' * 65536] * 17
        code, out, err, _ = self.run_script(['Downstream', 'modem.example'],
                                            response=Response('', chunks=chunks))
        self.assertEqual((code, out), (1, ''))
        self.assertIn('larger than 1048576 bytes', err)
        self.assertTrue(self.response.closed)

    def test_slow_response_is_abandoned_at_the_deadline(self):
        clock = iter([100.0, 100.0, 125.0])
        with mock.patch('time.monotonic', side_effect=lambda: next(clock)):
            code, out, err, _ = self.run_script(['Downstream', 'modem.example'],
                                                response=Response('', chunks=[b'a', b'b']))
        self.assertEqual((code, out), (1, ''))
        self.assertIn('longer than 20 seconds', err)

    def test_remote_text_cannot_add_output_fields(self):
        modem = page(table('Upstream', ['5', '6 x:1', '7'],
                           [('Power Level', ['41 dBmV', '42 dBmV', '43 extra:9'])]))
        code, out, _, _ = self.run_script(['Upstream', 'modem.example'], text=modem)
        self.assertEqual(code, 0)
        self.assertEqual(out, 'UpstreamPower5:41 UpstreamPower7:U\n')

    def test_upstream_output(self):
        code, out, _, _ = self.run_script(['Upstream', 'modem.example'])
        self.assertEqual(code, 0)
        self.assertEqual(out, ' '.join(f'UpstreamPower{n}:4{n}' for n in UP) + '\n')

    def test_stats_output(self):
        code, out, _, _ = self.run_script(['Stats', 'modem.example'])
        self.assertEqual(code, 0)
        expected = []
        for n in DOWN:
            expected += [f'StatsUnerrored{n}:100{n}', f'StatsCorrectable{n}:20{n}',
                         f'StatsUncorrectable{n}:3{n}']
        self.assertEqual(out, ' '.join(expected) + '\n')

    def test_channels_come_from_the_modem_table(self):
        modem = page(table('Upstream', ['14', '3', 'x'], [('Power Level', ['41 dBmV', '', '43 dBmV'])]))
        code, out, _, _ = self.run_script(['Upstream', 'modem.example'], text=modem)
        self.assertEqual(code, 0)
        self.assertEqual(out, 'UpstreamPower3:U UpstreamPower14:41 UpstreamPowerx:43\n')

    def test_missing_table_prints_empty_result_instead_of_a_traceback(self):
        code, out, err, _ = self.run_script(['Stats', 'modem.example'], text=page())
        self.assertEqual((code, out, err), (0, '\n', ''))

    def test_http_error_is_reported_on_stderr(self):
        code, out, err, _ = self.run_script(['Downstream', 'modem.example'], error='503 Server Error')
        self.assertEqual((code, out), (1, ''))
        self.assertIn('Unable to retrieve data from modem!', err)
        self.assertIn('503 Server Error', err)

    def test_usage_errors_are_reported_on_stderr(self):
        for args in (['Downstream'], ['Sideways', 'modem.example']):
            with self.subTest(args=args):
                code, out, err, get = self.run_script(args)
                self.assertEqual((code, out), (1, ''))
                self.assertIn('usage:', err)
                get.assert_not_called()


if __name__ == '__main__':
    unittest.main()
