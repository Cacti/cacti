"""Regressions for literal percent escaping in translation validation."""
import contextlib
import io
import unittest
from audit_po import _audit_pair


class PercentContracts(unittest.TestCase):
    def audit(self, source, translated):
        with contextlib.redirect_stdout(io.StringIO()):
            return _audit_pair(source, translated, "fixture")

    def test_escaped_conversion_letters_are_literal(self):
        self.assertEqual(self.audit("Literal %%s and %%d", "Literal %%s och %%d"), 0)

    def test_real_parameters_still_require_positions(self):
        self.assertGreater(self.audit("%s / %d", "%s / %d"), 0)

    def test_numbered_parameters_and_percent_literals_coexist(self):
        self.assertEqual(self.audit("%s / %d (100%%)", "%1$s / %2$d (100%%)"), 0)


if __name__ == "__main__":
    unittest.main()
