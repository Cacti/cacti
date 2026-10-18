#!/usr/bin/env python3
"""Load the pinned CI database image before starting any database container.

Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
"""
import argparse
import hashlib
import json
import os
from pathlib import Path
import subprocess
import tarfile
import tempfile


# The amd64 config digest comes from the platform manifest inside this pinned
# upstream index (amd64 manifest 5c6923b063c9c429e314b65e0a9363f31d64b68a83efabb45506b0f745facd9a).
# ci-images.yml copies the index to GHCR with preserved digests.
INDEX = 'sha256:2fdc84794931c0abde9ba6a8c9cb5a4635a02d851a103f0301a8b606dfc5cd98'
IMAGE_ID = 'sha256:8858f1021dff59974c9a9964407c85e23d424ba49ecbd515d9d121781e029d37'
MIRROR = 'ghcr.io/cacti/ci-images/mariadb@' + INDEX
PLATFORM = 'linux/amd64'
TAG = 'cacti-ci-mariadb:sha256-' + INDEX.split(':')[1]
CACHE_KEY = 'ci-mariadb-v1-linux-amd64-' + INDEX.split(':')[1]


def docker(*arguments, capture=False):
    result = subprocess.run(['docker', *arguments], check=True, text=True,
                            stdout=subprocess.PIPE if capture else None)
    return result.stdout.strip() if capture else None


def verify_archive(path):
    """Validate cached config bytes against the source pin, not cached metadata.

    Docker save/load does not reliably preserve RepoDigests. The config digest
    identifies the executable image and its uncompressed layer hashes instead.
    Docker load validates those layers before the image can be used.
    """
    with tarfile.open(path, 'r:*') as archive:
        manifest_member = archive.getmember('manifest.json')
        if not manifest_member.isfile():
            raise ValueError('Cache manifest must be a regular file')
        manifest = json.load(archive.extractfile(manifest_member))
        if not isinstance(manifest, list) or len(manifest) != 1:
            raise ValueError('Cache must contain exactly one image')
        member = archive.getmember(manifest[0]['Config'])
        if not member.isfile():
            raise ValueError('Cache image config must be a regular file')
        config = archive.extractfile(member).read()
        if 'sha256:' + hashlib.sha256(config).hexdigest() != IMAGE_ID:
            raise ValueError('Cached image does not match the pinned MariaDB config')


def prepare(cache_directory):
    cache_directory.mkdir(parents=True, exist_ok=True)
    archive = cache_directory / 'mariadb.tar'
    if archive.exists():
        verify_archive(archive)
        docker('image', 'load', '--input', str(archive))
        source = 'cache'
    else:
        # No mutable tag, Docker Hub fallback, or registry call on a cache hit.
        docker('pull', '--platform', PLATFORM, MIRROR)
        if docker('image', 'inspect', '--format={{.Id}}', MIRROR, capture=True) != IMAGE_ID:
            raise ValueError('GHCR image does not match the pinned MariaDB config')
        docker('image', 'tag', MIRROR, TAG)
        descriptor, temporary = tempfile.mkstemp(prefix='mariadb-', suffix='.tar', dir=cache_directory)
        os.close(descriptor)
        try:
            docker('image', 'save', '--output', temporary, TAG)
            verify_archive(temporary)
            Path(temporary).replace(archive)
        finally:
            Path(temporary).unlink(missing_ok=True)
        source = 'ghcr'
    if docker('image', 'inspect', '--format={{.Id}}', IMAGE_ID, capture=True) != IMAGE_ID:
        raise ValueError('Pinned MariaDB image is unavailable after preparation')
    return {'image': IMAGE_ID, 'source': source}


def emit(values):
    output = ''.join(f'{name}={value}\n' for name, value in values.items())
    print(output, end='')
    if os.environ.get('GITHUB_OUTPUT'):
        with open(os.environ['GITHUB_OUTPUT'], 'a', encoding='utf-8') as stream:
            stream.write(output)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('operation', choices=['metadata', 'prepare'])
    parser.add_argument('cache_directory', nargs='?', type=Path)
    args = parser.parse_args()
    if args.operation == 'metadata':
        emit({'key': CACHE_KEY, 'image': IMAGE_ID})
    elif args.cache_directory is None:
        parser.error('prepare requires a cache directory')
    else:
        emit(prepare(args.cache_directory))


if __name__ == '__main__':
    main()
