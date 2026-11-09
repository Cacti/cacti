"""Verify the release and development branch dependency update policy."""

import unittest
from pathlib import Path

import yaml

ROOT = Path(__file__).resolve().parents[2]
CONFIG = ROOT / '.github/dependabot.yml'
MAJOR_UPDATE = 'version-update:semver-major'


class DependabotConfigTest(unittest.TestCase):
    def setUp(self) -> None:
        self.updates = yaml.safe_load(CONFIG.read_text())['updates']

    @staticmethod
    def update_directories(update: dict[str, object]) -> list[str]:
        directories = update.get('directories')
        if isinstance(directories, list) and directories:
            return [str(directory) for directory in directories]
        directory = update.get('directory')
        if isinstance(directory, str):
            return [directory]
        raise AssertionError('Dependabot update is missing directory or directories')

    @staticmethod
    def update_name(update: dict[str, object]) -> str:
        directories = ','.join(DependabotConfigTest.update_directories(update))
        return f"{update['package-ecosystem']}:{directories}"

    @staticmethod
    def ignores_all_major_updates(update: dict[str, object]) -> bool:
        return any(
            rule.get('dependency-name') == '*'
            and MAJOR_UPDATE in rule.get('update-types', [])
            for rule in update.get('ignore', [])
        )

    def test_1_2_x_accepts_only_minor_and_patch_version_updates(self) -> None:
        release_updates = [
            update
            for update in self.updates
            if update.get('target-branch') == '1.2.x'
        ]

        self.assertTrue(release_updates)
        for update in release_updates:
            with self.subTest(update=self.update_name(update)):
                self.assertTrue(self.ignores_all_major_updates(update))
                for group in update['groups'].values():
                    self.assertEqual(
                        set(group['update-types']),
                        {'minor', 'patch'},
                    )

    def test_develop_keeps_major_version_updates_enabled(self) -> None:
        develop_updates = [
            update for update in self.updates if 'target-branch' not in update
        ]

        self.assertTrue(develop_updates)
        for update in develop_updates:
            with self.subTest(update=self.update_name(update)):
                self.assertFalse(self.ignores_all_major_updates(update))

    def test_1_2_x_keeps_composer_updates_enabled(self) -> None:
        release_jobs = {
            (update['package-ecosystem'], directory)
            for update in self.updates
            if update.get('target-branch') == '1.2.x'
            for directory in self.update_directories(update)
        }

        self.assertIn(('composer', '/'), release_jobs)
        self.assertIn(('composer', '/tests'), release_jobs)

    def test_symfony_stays_on_7_x_on_develop(self) -> None:
        symfony_rules = [
            (update, rule)
            for update in self.updates
            for rule in update.get('ignore', [])
            if rule.get('dependency-name') == 'symfony/*'
        ]

        self.assertEqual(len(symfony_rules), 1)
        update, rule = symfony_rules[0]
        self.assertNotIn('target-branch', update)
        self.assertEqual(update['package-ecosystem'], 'composer')
        self.assertEqual(update['directory'], '/')
        self.assertEqual(rule.get('versions'), ['>= 8.0.0'])


if __name__ == '__main__':
    unittest.main()
