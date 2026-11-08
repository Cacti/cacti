"""Run the loadavg.pl data input script against valid and invalid selectors."""
from pathlib import Path
import re
import shutil
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[2]
SCRIPT = ROOT / 'scripts' / 'loadavg.pl'
PERL = shutil.which('perl')


@unittest.skipIf(PERL is None, 'perl is not installed')
class LoadavgScriptTest(unittest.TestCase):
    def run_script(self, *args):
        result = subprocess.run([PERL, str(SCRIPT), *args], capture_output=True, text=True,
                                timeout=30, check=False)
        return result.returncode, result.stdout, result.stderr

    def test_invalid_selector_prints_u_and_fails(self):
        for arg in ('x', '5x', '-1', '1.5', ' 5'):
            with self.subTest(arg=arg):
                self.assertEqual(self.run_script(arg), (1, 'U', ''))

    def test_selector_prints_one_average(self):
        for arg in ('5', '15', '30'):
            with self.subTest(arg=arg):
                code, out, err = self.run_script(arg)
                self.assertEqual((code, err), (0, ''))
                self.assertRegex(out, r'^[0-9]+\.[0-9]+$')

    def test_no_selector_prints_all_three_averages(self):
        for args in ((), ('',)):
            with self.subTest(args=args):
                code, out, err = self.run_script(*args)
                self.assertEqual((code, err), (0, ''))
                self.assertEqual(len(re.findall(r'[0-9]+\.[0-9]+', out)), 3)


if __name__ == '__main__':
    unittest.main()
