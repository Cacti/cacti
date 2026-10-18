#!/usr/bin/env python3
"""Contracts for loading the pinned database image, without a live registry.

Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
"""
import hashlib
import io
import json
import os
from pathlib import Path
import subprocess
import sys
import tarfile
import tempfile
import unittest
from unittest.mock import patch

import ci_mariadb_image as image


class ImageCacheTests(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        self.cache = Path(self.directory.name)
        self.config = b'{"architecture":"amd64","os":"linux","rootfs":{"diff_ids":[]}}'
        self.image_id = 'sha256:' + hashlib.sha256(self.config).hexdigest()
        self.pin = patch.object(image, 'IMAGE_ID', self.image_id)
        self.pin.start()
        self.addCleanup(self.pin.stop)

    def archive(self, path, config=None, entries=1, link=False):
        with tarfile.open(path, 'w') as archive:
            for name, data in [('manifest.json', json.dumps([{'Config': 'config.json'}] * entries).encode()),
                               ('config.json', self.config if config is None else config)]:
                member = tarfile.TarInfo(name)
                member.size = len(data)
                if link and name == 'config.json':
                    member.type = tarfile.SYMTYPE
                    member.linkname = '/etc/passwd'
                archive.addfile(member, io.BytesIO(data))

    def test_hit_loads_archive_without_registry_access(self):
        self.archive(self.cache / 'mariadb.tar')
        with patch.object(image, 'docker', return_value=self.image_id) as docker:
            result = image.prepare(self.cache)
        self.assertEqual(result, {'image': self.image_id, 'source': 'cache'})
        self.assertEqual(docker.call_args_list[0].args,
                         ('image', 'load', '--input', str(self.cache / 'mariadb.tar')))
        self.assertFalse(any(call.args[0] == 'pull' for call in docker.call_args_list))

    def test_miss_pulls_only_pinned_ghcr_and_saves_verified_archive(self):
        def docker(*args, **kwargs):
            if args[:2] == ('image', 'save'):
                self.archive(args[3])
            return self.image_id if kwargs.get('capture') else None
        with patch.object(image, 'docker', side_effect=docker) as calls:
            result = image.prepare(self.cache)
        self.assertEqual(result['source'], 'ghcr')
        self.assertEqual(calls.call_args_list[0].args,
                         ('pull', '--platform', 'linux/amd64', image.MIRROR))
        image.verify_archive(self.cache / 'mariadb.tar')
        self.assertEqual(list(self.cache.iterdir()), [self.cache / 'mariadb.tar'])

    def test_wrong_cached_config_fails_before_docker_load(self):
        self.archive(self.cache / 'mariadb.tar', config=b'wrong image')
        with patch.object(image, 'docker') as docker, self.assertRaisesRegex(ValueError, 'pinned'):
            image.prepare(self.cache)
        docker.assert_not_called()

    def test_invalid_archive_layout_is_rejected(self):
        for entries, link in [(0, False), (2, False), (1, True)]:
            with self.subTest(entries=entries, link=link):
                self.archive(self.cache / 'mariadb.tar', entries=entries, link=link)
                with self.assertRaises(ValueError):
                    image.verify_archive(self.cache / 'mariadb.tar')

    def test_wrong_registry_config_is_not_cached(self):
        with patch.object(image, 'docker', return_value='sha256:wrong'), self.assertRaisesRegex(ValueError, 'GHCR'):
            image.prepare(self.cache)
        self.assertEqual(list(self.cache.iterdir()), [])

    def test_interrupted_save_does_not_publish_an_archive(self):
        def docker(*args, **kwargs):
            if args[:2] == ('image', 'save'):
                raise subprocess.CalledProcessError(1, 'docker save')
            return self.image_id if kwargs.get('capture') else None
        with patch.object(image, 'docker', side_effect=docker), self.assertRaises(subprocess.CalledProcessError):
            image.prepare(self.cache)
        self.assertEqual(list(self.cache.iterdir()), [])

    def test_load_failure_is_not_replaced_with_a_pull(self):
        self.archive(self.cache / 'mariadb.tar')
        with patch.object(image, 'docker', side_effect=subprocess.CalledProcessError(1, 'docker load')) as docker:
            with self.assertRaises(subprocess.CalledProcessError):
                image.prepare(self.cache)
        self.assertEqual(docker.call_count, 1)

    def test_unavailable_loaded_image_fails(self):
        self.archive(self.cache / 'mariadb.tar')
        with patch.object(image, 'docker', return_value=''), self.assertRaisesRegex(ValueError, 'unavailable'):
            image.prepare(self.cache)

    def test_docker_wrapper_checks_status_and_captures_identity(self):
        with patch.object(subprocess, 'run', return_value=subprocess.CompletedProcess([], 0, 'sha256:abc\n')) as run:
            self.assertEqual(image.docker('image', 'inspect', capture=True), 'sha256:abc')
            self.assertTrue(run.call_args.kwargs['check'])
            self.assertIsNone(image.docker('pull', image.MIRROR))

    def test_cli_metadata_and_prepare_outputs(self):
        output = self.cache / 'output'
        with patch.dict(os.environ, {'GITHUB_OUTPUT': str(output)}), patch('sys.stdout', new_callable=io.StringIO):
            with patch.object(sys, 'argv', ['image', 'metadata']):
                image.main()
            with patch.object(sys, 'argv', ['image', 'prepare', str(self.cache)]), patch.object(image, 'prepare', return_value={'source': 'cache'}):
                image.main()
        self.assertIn('key=' + image.CACHE_KEY, output.read_text())
        self.assertIn('source=cache', output.read_text())
        with patch.object(sys, 'argv', ['image', 'prepare']), patch('sys.stderr', new_callable=io.StringIO), self.assertRaises(SystemExit):
            image.main()


if __name__ == '__main__':
    unittest.main()
