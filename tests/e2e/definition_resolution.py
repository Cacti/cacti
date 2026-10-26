#!/usr/bin/env python3
"""Run definition manager browser tests against disposable Cacti and MariaDB.

Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.
"""
import argparse
import os
from pathlib import Path
import shutil
import secrets
import socket
import subprocess
import tempfile
import time
import urllib.request


def run(*args, **kwargs):
    return subprocess.run(args, check=True, **kwargs)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--kind', choices=['cdef', 'vdef'], required=True)
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    root = Path(__file__).resolve().parents[2]
    output = args.output.resolve()
    output.mkdir(parents=True, exist_ok=True)
    (output / 'php').mkdir(exist_ok=True)
    container = f'cacti-{args.kind}-browser-{os.getpid()}'
    server = None
    db_password = secrets.token_urlsafe(32)
    login_password = secrets.token_urlsafe(32)
    try:
        run('docker', 'run', '--detach', '--rm', '--name', container,
            '--publish', '127.0.0.1::3306', '--tmpfs', '/var/lib/mysql',
            '--env', 'MARIADB_ROOT_PASSWORD',
            '--env', 'MARIADB_DATABASE=cacti_test',
            os.environ.get('CACTI_DEFINITION_DB_IMAGE', 'mariadb:11.4.13'),
            env=dict(os.environ, MARIADB_ROOT_PASSWORD=db_password))
        for attempt in range(60):
            ready = subprocess.run(['docker', 'exec', container, 'healthcheck.sh',
                                    '--connect', '--innodb_initialized'],
                                   stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
            if ready.returncode == 0:
                break
            time.sleep(1)
        else:
            raise RuntimeError('Disposable MariaDB did not become ready')
        port = subprocess.check_output(['docker', 'port', container, '3306'], text=True).strip().split(':')[-1]
        with (root / 'cacti.sql').open('rb') as schema:
            run('docker', 'exec', '-i', '--env', 'MYSQL_PWD', container, 'mariadb', '-uroot',
                'cacti_test', stdin=schema, env=dict(os.environ, MYSQL_PWD=db_password))
        with tempfile.TemporaryDirectory(prefix='cacti-definition-browser-') as temporary:
            app = Path(temporary) / 'app'
            shutil.copytree(root, app, ignore=shutil.ignore_patterns(
                '.git', 'node_modules', 'vendor', 'log', 'cache', 'rra',
                'test-results', 'playwright-report', '__pycache__'))
            (app / 'include/vendor').symlink_to(root / 'include/vendor', target_is_directory=True)
            for name in ['log', 'cache', 'rra']:
                (app / name).mkdir()
            env = dict(os.environ, CACTI_DEFINITION_APP=str(app), CACTI_DEFINITION_DB_PORT=port,
                       CACTI_DEFINITION_DB_PASSWORD=db_password, CACTI_DEFINITION_LOGIN_PASSWORD=login_password)
            run('php', str(app / 'tests/e2e/definition_resolution_setup.php'), env=env)
            with socket.socket() as listener:
                listener.bind(('127.0.0.1', 0))
                http_port = listener.getsockname()[1]
            origin = f'http://127.0.0.1:{http_port}'
            php_args = ['php', '-d', 'memory_limit=512M']
            if os.environ.get('CACTI_E2E_XDEBUG_EXTENSION'):
                php_args += ['-d', 'zend_extension=' + os.environ['CACTI_E2E_XDEBUG_EXTENSION']]
            php_args += ['-d', 'auto_prepend_file=' + str(app / 'tests/Helpers/RequestCoverage.php'),
                         '-S', f'127.0.0.1:{http_port}', '-t', str(app)]
            with (output / 'server.log').open('w') as log:
                server = subprocess.Popen(php_args, cwd=app, stdout=log, stderr=log,
                                          env=dict(env, XDEBUG_MODE='coverage',
                                                   CACTI_PR_RAW_COVERAGE=str(output / 'php')))
                try:
                    for attempt in range(60):
                        if server.poll() is not None:
                            raise RuntimeError('PHP server exited; see server.log')
                        try:
                            with urllib.request.urlopen(origin + '/index.php', timeout=1) as response:
                                if response.status == 200:
                                    break
                        except OSError:
                            time.sleep(0.25)
                    else:
                        raise RuntimeError('Cacti web server did not become ready')
                    run('node', str(root / 'tests/e2e/node_modules/playwright/cli.js'), 'test',
                        'tests/definition-resolution.spec.ts', '--project=chromium', '--workers=1',
                        '--reporter=line,json', '--output=' + str(output / 'browser'),
                        cwd=root / 'tests/e2e', env=dict(env, CACTI_BASE_URL=origin,
                        CACTI_DEFINITION_KIND=args.kind,
                        PLAYWRIGHT_JSON_OUTPUT_FILE=str(output / 'browser.json')))
                finally:
                    server.terminate()
                    server.wait(timeout=10)
                    server = None
    finally:
        if server is not None:
            server.terminate()
            server.wait(timeout=10)
        subprocess.run(['docker', 'stop', container], stdout=subprocess.DEVNULL, check=False)


if __name__ == '__main__':
    main()
