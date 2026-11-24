"""Verify the changed-code runner's probe groups with recording doubles.

Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
"""
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

TOOLS = Path(__file__).resolve().parent
RUNNER = TOOLS / 'run_pr_change_tests.sh'

# The fixtures and specs that existed when the probes moved into
# pr_change_tests.d. A group added later probes files this list does not
# create, so it adds a file to that directory and leaves this test alone.
FIXTURES = [
    'Core/' + name + '.php' for name in [
        'ScriptSubstitutionMatrix', 'DeviceCollectorMessages', 'ReportMessageDelivery', 'ExternalLinkMessage',
        'MessageFormatting', 'TrustedActionMessages', 'PackageManifestMatrix', 'PackageSaveMatrix',
        'ConstantTimeGuardMatrix', 'GraphDrilldownMarkup', 'HtmlGraphMarkup', 'CardWidgetMarkup',
        'SpikekillMutationMatrix', 'SpikekillFloatDebug', 'RowGuardMatrix', 'RemoveSpikesHelp',
        'SpikekillRequestGate', 'RraContainmentMatrix', 'Boost/FailedDataSourceIsolation', 'TableColumnSizing']
] + ['BoostBulkDrainFailureProbe.php', 'BoostOnDemandFailureProbe.php', 'BoostSourceExistenceProbe.php']
SPECS = ['graph-drilldown.spec.js', 'graph-spikekill.spec.js', 'filter-dialog-theme.spec.js',
         'table-column-widths.spec.js', 'develop-theme-correctness.spec.ts', 'develop-theme-cleanup.spec.ts']

TABLE_REQUESTS = ['{}', '{"action":"go"}', '{"action":"change","tab":"devices"}',
                  '{"action":"reset","report":"summary"}', '{"action":"edit"}']
ALWAYS = [
    ('PackageArchive', 'PackageArchive.php'), ('PackageDiffBoundary', 'PackageDiffBoundary.php'),
    ('PackageRepositoryWorkspace', 'PackageRepositoryWorkspace.php'),
    ('PackageImportFileStatus', 'PackageImportFileStatus.php')]
COMMANDS = [
    ('configured-commands', 'ConfiguredCommands.php'), ('deprecated-rebuild', 'ConfiguredCommands.php', 'deprecated'),
    ('display-validation', 'DisplayAndValidation.php'), ('input-method-save', 'DisplayAndValidation.php', 'save')]
# One entry per probe: the coverage label, the fixture and its arguments.
PROBES = [(name[5:-4], name[5:]) for name in FIXTURES[:15]] + [
    ('RemoveSpikesHelp', 'RemoveSpikesHelp.php'),
    *[('row-guard-' + mode, 'RowGuardMatrix.php', mode) for mode in ['auth-changepassword', 'add-device', 'reports-edit']],
    ('spike-get', 'SpikekillRequestGate.php', 'GET', '', 'mutation'),
    ('spike-dry', 'SpikekillRequestGate.php', 'GET', 'dryrun', 'mutation'),
    ('spike-post', 'SpikekillRequestGate.php', 'POST', '', 'mutation'),
    ('spike-setting-get', 'SpikekillRequestGate.php', 'GET', '', 'settings'),
    ('spike-setting-post', 'SpikekillRequestGate.php', 'POST', '', 'settings'),
    *ALWAYS,
    ('rra-normal', 'RraContainmentMatrix.php', 'normal'), ('rra-missing', 'RraContainmentMatrix.php', 'missing'),
    ('boost-failed-data-source-isolation', 'Boost/FailedDataSourceIsolation.php'),
    *[('boost-bulk-drain-' + scenario, '../BoostBulkDrainFailureProbe.php', scenario)
      for scenario in ['middle', 'boundary', 'tail', 'none']],
    *[('boost-on-demand-' + scenario, '../BoostOnDemandFailureProbe.php', scenario)
      for scenario in ['first', 'middle', 'ok-substring', 'archive-copy', 'temp-create', 'tail', 'ack-create',
                       'ack-insert', 'ack-delete', 'archive-ok', 'ack-empty']],
    *[('boost-source-existence-' + scenario, '../BoostSourceExistenceProbe.php', scenario)
      for scenario in ['deleted', 'query-failed']],
    *[('table-%d' % (index * 4 + mode * 2 + title), 'TableColumnSizing.php', request, ['table', 'div'][mode],
       ['', 'Title'][title])
      for index, request in enumerate(TABLE_REQUESTS) for mode in range(2) for title in range(2)],
    *COMMANDS]


def record(runner, fixtures, specs, changed=None):
    """Run a runner against doubles and return its status and their calls."""
    with tempfile.TemporaryDirectory() as temporary:
        root = Path(temporary)
        repository = root / 'repo'
        tools = root / 'tools'
        tools.mkdir()
        log = root / 'log'
        log.write_text('')
        for name, line in [
                ('php', 'printf "%s\\t" probe "${CACTI_COVERAGE_FILE##*/}" "${@:5}"'),
                ('python', 'printf "%s\\t" python "$@"'),
                ('npx', 'printf "%s\\t" browser "$CACTI_THEME_COVERAGE" "${@:3}"')]:
            (tools / name).write_text('#!' + shutil.which('bash') + '\n{ ' + line + '; echo; } >> "$CACTI_TEST_LOG"\n')
            (tools / name).chmod(0o700)
        env = dict(os.environ, PATH=str(tools) + os.pathsep + os.environ['PATH'], CACTI_TEST_LOG=str(log),
                   CACTI_TEST_PHP=str(tools / 'php'), CACTI_TEST_PYTHON=str(tools / 'python'),
                   CACTI_COVERAGE_DIR=str(root / 'coverage'))

        def git(*arguments):
            return subprocess.check_output(
                ['git', '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=/dev/null',
                 '-c', 'user.name=Coverage fixture', '-c', 'user.email=fixture@example.invalid',
                 *arguments], cwd=repository, env=env, text=True).strip()

        def write(name):
            path = repository / name
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text('fixture\n')

        write('README.md')
        for name in fixtures:
            write('tests/fixtures/' + name)
        for name in specs:
            write('tests/e2e/tests/' + name)
        git('init', '--quiet')
        git('add', '.')
        git('commit', '--quiet', '-m', 'fixture base')
        if changed:
            write(changed)
            git('add', '.')
            git('commit', '--quiet', '-m', 'fixture change')
        result = subprocess.run(['bash', str(runner), git('rev-list', '--max-parents=0', 'HEAD')], cwd=repository,
                                env=env, capture_output=True, text=True)
        calls = [tuple(line.split('\t')[:-1]) for line in log.read_text().splitlines()]
        return result, calls


def expected(entries):
    return [(label + '.json', 'tests/fixtures/Core/' + fixture, *arguments) for label, fixture, *arguments in entries]


class ChangedCodeProbeGroupTest(unittest.TestCase):
    def run_groups(self, fixtures, specs, changed=None):
        """Return the known probes, the report's known inputs and the browser call."""
        result, calls = record(RUNNER, fixtures, specs, changed)
        self.assertEqual(result.returncode, 0, result.stderr)
        # A group added after the move may probe unconditionally, so compare
        # only the probes that existed at the move.
        known = set(expected(PROBES))
        probes = [call[1:] for call in calls if call[0] == 'probe' and call[1:] in known]
        python = [call[1:] for call in calls if call[0] == 'python']
        self.assertEqual(python[0], ('tests/tools/test_report_changed_coverage.py',))
        self.assertEqual(python[1][:2], ('tests/tools/report_changed_coverage.py', '--base'))
        self.assertEqual(python[1][3], '--php-coverage')
        labels = {probe[0] for probe in known}
        reports = [argument.split('/coverage/')[-1] for argument in python[1][4:]]
        self.assertEqual([name for name in reports if name in labels], [probe[0] for probe in probes])
        return probes, reports, [call[1:] for call in calls if call[0] == 'browser']

    def test_every_probe_runs_in_the_order_it_ran_before_the_move(self):
        probes, reports, browser = self.run_groups(FIXTURES, SPECS)
        self.assertEqual(probes, expected(PROBES))
        self.assertEqual(reports[-2:], ['--browser-root', 'tests/e2e/test-results'])
        self.assertEqual(browser, [('0', 'test', 'tests/graph-drilldown.spec.js', 'tests/graph-spikekill.spec.js',
                                    'tests/filter-dialog-theme.spec.js', 'tests/table-column-widths.spec.js',
                                    '--workers=2', '--reporter=line')])

    def test_missing_fixtures_leave_only_the_unconditional_probes(self):
        probes, reports, browser = self.run_groups([], [])
        self.assertEqual(probes, expected(ALWAYS + COMMANDS))
        self.assertNotIn('--browser-root', reports)
        self.assertEqual(browser, [])

    def test_theme_change_appends_the_theme_specs_after_the_group_specs(self):
        _, _, browser = self.run_groups([], SPECS, 'include/themes/modern/main.css')
        self.assertEqual(browser[0][:3], ('1', 'test', 'tests/graph-drilldown.spec.js'))
        self.assertEqual(browser[0][-4:-2], ('tests/develop-theme-correctness.spec.ts', 'tests/develop-theme-cleanup.spec.ts'))


class ProbeGroupLoaderTest(unittest.TestCase):
    def load(self, groups):
        """Source a probe group directory and return the result."""
        with tempfile.TemporaryDirectory() as temporary:
            directory = Path(temporary) / 'groups.d'
            if groups is not None:
                directory.mkdir()
                for name, text in groups.items():
                    (directory / name).write_text(text)
            return subprocess.run(
                ['bash', '-c', 'set -euo pipefail; source "$1"; source_probe_groups "$2"; echo loaded',
                 'loader', str(TOOLS / 'probe_groups.sh'), str(directory)], capture_output=True, text=True,
                # A dictionary-order locale, where one is installed, would sort C after b.
                env=dict(os.environ, LC_ALL='en_US.UTF-8'))

    def test_groups_run_in_byte_order_whatever_the_locale(self):
        groups = {name + '.sh': 'echo ' + name + '\n' for name in ['b', 'C', '20-x', 'a', '100-y', 'Z_z']}
        result = self.load(groups)
        self.assertEqual(result.stdout.split(), ['100-y', '20-x', 'C', 'Z_z', 'a', 'b', 'loaded'])

    def test_missing_or_empty_directory_stops_the_runner(self):
        for groups, message in [(None, 'is missing'), ({}, 'is empty')]:
            with self.subTest(message=message):
                result = self.load(groups)
                self.assertEqual((result.returncode, result.stdout), (1, ''))
                self.assertIn(message, result.stderr)

    def test_unexpected_entry_stops_the_runner_before_any_group_runs(self):
        for name in ['notes.txt', 'group.sh.orig', 'group.sh~', 'two words.sh']:
            with self.subTest(name=name):
                result = self.load({'010-first.sh': 'echo first\n', name: 'echo stray\n'})
                self.assertEqual((result.returncode, result.stdout), (1, ''))
                self.assertIn('is not a regular file named', result.stderr)

    def test_syntax_error_stops_the_runner_before_any_group_runs(self):
        result = self.load({'010-first.sh': 'echo first\n', '020-broken.sh': 'if true; then\n'})
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(result.stdout, '')
        self.assertIn('020-broken.sh', result.stderr)

    def test_failing_group_stops_the_runner(self):
        result = self.load({'010-first.sh': 'echo first\n', '020-fails.sh': 'false\n', '030-last.sh': 'echo last\n'})
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(result.stdout, 'first\n')


if __name__ == '__main__':
    unittest.main()
