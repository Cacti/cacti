"""Verify the sink inventory records every line of a wrapped sink call."""

from pathlib import Path
from unittest import TestCase, main
import subprocess
import tempfile

SCRIPT = Path(__file__).resolve().parents[2] / 'tests/security/join_wrapped_statement.awk'

SOURCE = '''<?php
\texec("$dump " .
\t\tcacti_escapeshellarg($database) .
\t\t' > out', $output);
\texec('echo (one', $output);
\t$x = 1;
'''


class JoinWrappedStatementTest(TestCase):
    def join(self, records):
        """Run the joiner over ripgrep-style records and return its lines."""
        result = subprocess.run(['awk', '-f', str(SCRIPT)], input=records, capture_output=True, text=True, check=True)
        return result.stdout.splitlines()

    def test_wrapped_call_keeps_continuation_arguments(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'sink.php'
            path.write_text(SOURCE)

            lines = self.join(f'{path}:2:\texec("$dump " .\n')

        self.assertEqual(lines, [f"{path}:2:\texec(\"$dump \" . cacti_escapeshellarg($database) . ' > out', $output);"])

    def test_dropping_escaping_on_a_continuation_line_changes_the_record(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'sink.php'
            path.write_text(SOURCE)
            before = self.join(f'{path}:2:\texec("$dump " .\n')
            path.write_text(SOURCE.replace('cacti_escapeshellarg($database)', '$database'))
            after = self.join(f'{path}:2:\texec("$dump " .\n')

        self.assertNotEqual(before, after)

    def test_parentheses_inside_strings_do_not_open_a_call(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'sink.php'
            path.write_text(SOURCE)
            record = f"{path}:5:\texec('echo (one', $output);\n"

            self.assertEqual(self.join(record), [record.rstrip('\n')])


if __name__ == '__main__':
    main()
