"""Exercise the page crawler's actual log filter with routine audit output.

Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
"""
from pathlib import Path
import subprocess
import tempfile
import unittest


class CrawlLogFilterTest(unittest.TestCase):
    def filtered(self, text):
        source = Path(__file__).with_name('check_all_pages.sh').read_text()
        start = source.index('FILTERED_LOG=')
        stop = source.index('\n# ---', start)
        with tempfile.TemporaryDirectory() as temporary:
            log = Path(temporary) / 'cacti.log'
            log.write_text(text)
            script = ('CACTI_LOG=$1\n' + source[start:stop] +
                      '\nprintf "%s" "$FILTERED_LOG"\n')
            return subprocess.check_output(
                ['bash', '-c', script, 'filter', str(log)], text=True)

    def test_routine_audit_purge_counts_are_accepted(self):
        for count in (0, 19):
            with self.subTest(count=count):
                self.assertEqual(self.filtered(
                    '11-06-2026 19:00:02 - POLLER: Poller[1] PID[14739] '
                    f'NOTE: Purged {count} Audit Log Records from Cacti\n'), '')

    def test_errors_and_unexpected_output_remain_visible(self):
        for message in ('ERROR: Audit purge failed',
                        'WARNING: Audit cleanup failed',
                        'unexpected output',
                        'NOTE: Purged 0 Audit Log Records from Cacti EXTRA'):
            with self.subTest(message=message):
                line = ('11-06-2026 19:00:02 - POLLER: Poller[1] '
                        'PID[14739] ' + message)
                self.assertEqual(self.filtered(line + '\n'), line)


if __name__ == '__main__':
    unittest.main()
