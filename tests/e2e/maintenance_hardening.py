#!/usr/bin/env python3
"""Exercise maintenance utility and manager hardening in a disposable checkout and database.

Requires native PHP with Cacti extensions, Docker, and an existing mariadb:11.8
image. Never run against an installed checkout: include/config.php must be absent.
Run once with CACTI_SESSION_MODE=files and once with CACTI_SESSION_MODE=database.
"""
# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
import http.cookiejar
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import socket
import subprocess
import time
import tempfile
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(os.environ.get('CACTI_AUTH_ROOT', Path(__file__).resolve().parents[2]))
OUT = Path(tempfile.mkdtemp(prefix='cacti-maintenance-hardening-'))
(OUT / 'sessions').mkdir(mode=0o700)
print('Evidence directory: ' + str(OUT), flush=True)
NAME = 'cacti-maintenance-hardening-' + str(os.getpid())
env = os.environ.copy()
env['MARIADB_ROOT_PASSWORD'] = secrets.token_hex(24)
env['CACTI_AUTH_PASSWORD'] = secrets.token_hex(20)
env['CACTI_AUTH_NEW_PASSWORD'] = secrets.token_hex(20) + 'Az!'
env['CACTI_SESSION_MODE'] = os.environ.get('CACTI_SESSION_MODE', 'files')
env['MYSQL_PWD'] = env['MARIADB_ROOT_PASSWORD']
env['CACTI_AUDIT_DB_PASSWORD'] = env['MARIADB_ROOT_PASSWORD']
results = {}

def run(args: list[str], **kwargs: object) -> subprocess.CompletedProcess[bytes]:
    return subprocess.run(args, env=env, check=True, capture_output=True, **kwargs)

def sql(statement: str) -> str:
    return run(['docker', 'exec', '-i', '-e', 'MYSQL_PWD', NAME, 'mariadb', '-uroot', '-N', '-B', 'cacti'], input=statement.encode()).stdout.decode().strip()

def record(name: str, value: bool | dict[str, bool]) -> None:
    results[name] = value
    print(name + ': ' + json.dumps(value), flush=True)

def free_port() -> int:
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0))
        return s.getsockname()[1]

webport = free_port()
server = None
created = False
config = ROOT / 'include/config.php'
assert not config.exists()
try:
    run(['docker', 'run', '--pull=never', '-d', '--name', NAME, '-p', '127.0.0.1::3306', '-e', 'MARIADB_ROOT_PASSWORD', 'mariadb:11.8', '--character-set-server=utf8mb4', '--collation-server=utf8mb4_unicode_ci'])
    created = True
    port = run(['docker', 'port', NAME, '3306']).stdout.decode().strip().rsplit(':', 1)[1]
    env['CACTI_AUDIT_DB_PORT'] = port
    for _ in range(100):
        ready = subprocess.run(['docker', 'exec', NAME, 'healthcheck.sh', '--connect', '--innodb_initialized'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        if ready.returncode == 0:
            break
        time.sleep(0.3)
    else:
        raise RuntimeError('Disposable database did not become ready')
    run(['docker', 'exec', '-i', '-e', 'MYSQL_PWD', NAME, 'mariadb', '-uroot'], input=b'CREATE DATABASE cacti CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
    sql((ROOT / 'cacti.sql').read_text())
    config.write_text("<?php\n$database_type='mysql';\n$database_hostname='127.0.0.1';\n$database_port=(int)getenv('CACTI_AUDIT_DB_PORT');\n$database_default='cacti';\n$database_username='root';\n$database_password=getenv('CACTI_AUDIT_DB_PASSWORD');\n$url_path='/';\n$poller_id=1;\n$proxy_headers=['HTTP_X_FORWARDED_FOR'];\n$cacti_db_session=getenv('CACTI_SESSION_MODE') === 'database';\n")
    php = run(['php', '-r', 'print PHP_BINARY;']).stdout.decode()
    sql("UPDATE version SET cacti='1.2.32'; UPDATE user_auth SET must_change_password='' WHERE id=1; REPLACE INTO settings (name,value) VALUES ('path_php_binary','" + php + "'),('install_step','1'),('cron_interval','300'),('path_cactilog','" + str(OUT / 'canary.log') + "');")
    for tool in ['rrdtool', 'snmpwalk', 'snmpget', 'snmpbulkwalk', 'snmpgetnext', 'snmptrap', 'spine']:
        sql("REPLACE INTO settings (name,value) VALUES ('path_" + tool + "','/usr/bin/true');")
    server = subprocess.Popen([php, '-d', 'session.save_path=' + str(OUT / 'sessions'), '-d', 'display_errors=0', '-d', 'log_errors=0', '-S', f'127.0.0.1:{webport}', '-t', str(ROOT)], env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    for _ in range(100):
        try:
            socket.create_connection(('127.0.0.1', webport), 0.2).close()
            break
        except OSError:
            time.sleep(0.1)
    password_hash = run([php, '-r', "print password_hash(getenv('CACTI_AUTH_PASSWORD'), PASSWORD_DEFAULT);"]).stdout.decode()
    sql("UPDATE user_auth SET password='" + password_hash + "', must_change_password='', password_change='on', login_opts=2 WHERE id=1; REPLACE INTO settings(name,value) VALUES ('auth_method','1'),('guest_user','0'),('auth_cache_enabled','on'),('secpass_lockfailed','3'),('secpass_unlocktime','30'),('secpass_forceold',''),('csrf_secret','" + secrets.token_hex(32) + "');")
    # Clone a synthetic account and permissions from the fresh schema's administrator.
    sql("CREATE TEMPORARY TABLE audit_user AS SELECT * FROM user_auth WHERE id=1; UPDATE audit_user SET id=9001,username='audit_subject'; INSERT INTO user_auth SELECT * FROM audit_user; INSERT INTO user_auth_realm SELECT realm_id,9001 FROM user_auth_realm WHERE user_id=1;")
    class NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, *args: object) -> None: return None
    class Client:
        def __init__(self) -> None:
            self.jar = http.cookiejar.CookieJar()
            self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar), NoRedirect())
            self.csrf = ''
            self.headers = {}
        def request(self, path: str, post: dict[str, str] | None = None,
                    headers: dict[str, str] | None = None) -> tuple[int, str]:
            data = urllib.parse.urlencode(post).encode() if post is not None else None
            try:
                response = self.opener.open(urllib.request.Request(f'http://127.0.0.1:{webport}' + path, data=data, headers=headers or {}), timeout=30)
            except urllib.error.HTTPError as e: response = e
            self.headers = response.headers
            body = response.read().decode(errors='replace')
            match = re.search(r"csrfMagicToken\s*=\s*['\"]([^'\"]+)",body)
            if match: self.csrf=match[1]
            return response.code,body
        def login(self, username: str = 'audit_subject', remember: bool = False,
                  password: str | None = None) -> tuple[int, str]:
            self.request('/index.php')
            assert self.csrf, 'No login CSRF token'
            post={'action':'login','login_username':username,'login_password':password or env['CACTI_AUTH_PASSWORD'],'__csrf_magic':self.csrf}
            if remember: post['remember_me']='on'
            status,body=self.request('/index.php',post)
            return status,body
        def update(self, value: str) -> bool:
            self.request('/auth_profile.php')
            self.request('/auth_profile.php',{'action':'update_data','tab':'general','name':'full_name','value':value,'__csrf_magic':self.csrf})
            return sql("SELECT full_name FROM user_auth WHERE id=9001")==value
        def drop_session(self) -> None:
            for cookie in list(self.jar):
                if cookie.name=='Cacti': self.jar.clear(cookie.domain,cookie.path,cookie.name)
    subject=Client()
    status,_=subject.login()
    assert status == 302, 'Control login failed'
    actions=['clear_poller_cache','rebuild_resource_cache','clear_logfile','purge_logfile','purge_data_source_statistics','rebuild_snmpagent_cache']
    canary=OUT/'canary.log'
    canary.write_text('synthetic log preservation control\n')
    for action in actions:
        status,_=subject.request('/utilities.php?'+urllib.parse.urlencode({'action':action}))
        record(action+'_get_refused',status==405 and subject.headers.get('Allow')=='POST')
        assert status==405, action+' GET status '+str(status)
        status,_=subject.request('/utilities.php',{'action':action,'__csrf_magic':'invalid'})
        record(action+'_invalid_token_refused',status==302 and subject.headers.get('Location') in ['/utilities.php','/rrdcleaner.php'])
        assert status==302, action+' invalid token status '+str(status)
    record('rejected_log_actions_preserve_file','synthetic log preservation control' in canary.read_text())
    for action in ['remall','arcall']:
        status,_=subject.request('/rrdcleaner.php?'+urllib.parse.urlencode({'action':action,'raction':'1','clear':'1'}))
        record(action+'_get_refused',status==405)
        assert status==405, action+' GET status '+str(status)
        status,_=subject.request('/rrdcleaner.php',{'action':action,'raction':'1','clear':'1','__csrf_magic':'invalid'})
        record(action+'_invalid_token_refused',status==302 and subject.headers.get('Location') in ['/utilities.php','/rrdcleaner.php'])
        assert status==302, action+' invalid token status '+str(status)
    subject.request('/utilities.php')
    status,_=subject.request('/utilities.php',{'action':'clear_logfile','__csrf_magic':subject.csrf})
    record('authorized_log_clear',status==200 and 'synthetic log preservation control' not in canary.read_text() and 'Log Cleared' in canary.read_text())
    sql("INSERT INTO snmpagent_cache(oid,name,mib,kind) VALUES ('1.3.6.1.4.1.99999.1','quoted-notification','MIB-control','Notification'),('1.3.6.1.4.1.99999.2','other-notification','OTHER-control','Notification');")
    # The receiver edit page uses this fixture only to look up notification subscriptions.
    sql("INSERT INTO snmpagent_managers (id,hostname,description,max_log_size,snmp_username,snmp_password,snmp_auth_protocol,snmp_priv_passphrase,snmp_priv_protocol,snmp_message_type) VALUES (9001,'127.0.0.1','synthetic receiver',10,'','','','','',1)")
    query=urllib.parse.urlencode({'action':'edit','tab':'notifications','id':'9001','mib':'MIB-control','header':'false'})
    status,body=subject.request('/managers.php?'+query)
    record('mib_filter_matches_only_selected_notification',status==200 and 'quoted-notification' in body and 'other-notification' not in body)
    sql("INSERT INTO snmpagent_notifications_log(time,severity,manager_id,notification,mib,varbinds) VALUES (1,1,9001,'quoted-log','TEST-MIB',CONCAT('MIB',CHAR(39),'control')),(1,2,9001,'other-log','OTHER-control','different')")
    query=urllib.parse.urlencode({'action':'edit','tab':'logs','id':'9001','severity':'1','filter':"MIB'control",'header':'false'})
    status,body=subject.request('/managers.php?'+query)
    record('quoted_log_filter_keeps_severity_scope',status==200 and 'quoted-log' in body and 'other-log' not in body)
finally:
    if server is not None:
        server.terminate(); server.wait(timeout=10)
    if config.exists(): config.unlink()
    shutil.rmtree(OUT / 'sessions')
    if created: subprocess.run(['docker','rm','-fv',NAME],check=True,stdout=subprocess.DEVNULL)
    (OUT / 'http-results.json').write_text(json.dumps(results,indent=2)+'\n')
for name,value in results.items():
    assert value is True,name
print('All maintenance hardening checks passed.')
