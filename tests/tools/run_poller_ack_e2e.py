# Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
import argparse
import hashlib
import json
import os
from pathlib import Path
import subprocess
import tarfile
import time

parser=argparse.ArgumentParser()
parser.add_argument('--image',default='cacti-e2e:latest')
parser.add_argument('--database-image',default='mariadb:10.6')
parser.add_argument('--output',required=True)
args=parser.parse_args()
ROOT=Path(__file__).resolve().parents[2]
OUT=Path(args.output).resolve()
OUT.mkdir(parents=True,exist_ok=True)
client='mysql' if args.database_image.startswith('mysql:') or args.database_image=='mariadb:10.3' else 'mariadb'
NAME='cacti-ack-full-' + str(os.getpid())
DB=NAME+'-db'; APP=NAME+'-app'; NETWORK=NAME+'-net'
def run(*args,**kwargs):
    return subprocess.run(args,text=True,check=True,**kwargs)

started=[]
try:
    run('docker','network','create','--internal',NETWORK,capture_output=True)
    run('docker','run','-d','--name',DB,'--network',NETWORK,'--network-alias','cacti-db','-e','MYSQL_ROOT_PASSWORD=ack-e2e','-e','MYSQL_DATABASE=cacti_poller_ack_e2e',args.database_image,'--max-heap-table-size=268435456','--tmp-table-size=268435456',capture_output=True)
    started.append(DB)
    run('docker','run','-d','--name',APP,'--network',NETWORK,'-e','DB_HOST=cacti-db','-e','DB_NAME=cacti_poller_ack_e2e','-e','DB_USER=root','-e','DB_PASS=ack-e2e','--entrypoint','sleep',args.image,'infinity',capture_output=True)
    started.append(APP)
    (OUT/'containers.txt').write_text(DB+'\n'+APP+'\n'+NETWORK+'\n')
    for _ in range(60):
        r=subprocess.run(['docker','exec','-e','MYSQL_PWD=ack-e2e',DB,client,'-h127.0.0.1','-uroot','-e','SELECT 1'],capture_output=True)
        if r.returncode==0: break
        time.sleep(1)
    else: raise RuntimeError('Database readiness timeout')
    files=set(run('git','-C',str(ROOT),'ls-files',capture_output=True).stdout.splitlines())
    files.add('tests/e2e/docker/probes/probe_poller_ack.php')
    files.update(str(p.relative_to(ROOT)) for p in (ROOT/'include/vendor').rglob('*') if p.is_file())
    archive=OUT/'source.tar'
    with tarfile.open(archive,'w') as tar:
        for path in sorted(files):
            if (ROOT/path).is_file(): tar.add(ROOT/path,arcname=path,recursive=False)
    run('docker','cp',str(archive),APP+':/tmp/ack-source.tar')
    run('docker','exec',APP,'tar','-xf','/tmp/ack-source.tar','-C','/var/www/html')
    run('docker','exec',APP,'php','/var/www/html/tests/e2e/docker/write-config.php','/var/www/html/include/config.php.dist','/tmp/ack-config.php')
    run('docker','exec',APP,'cp','/tmp/ack-config.php','/var/www/html/include/config.php')
    with (ROOT/'cacti.sql').open() as schema:
        run('docker','exec','-i','-e','MYSQL_PWD=ack-e2e',DB,client,'--named-commands','-uroot','cacti_poller_ack_e2e',stdin=schema)
    with (OUT/'install.log').open('w') as log:
        run('docker','exec','-w','/var/www/html',APP,'php','cli/install_cacti.php','--accept-eula','--install','--mode=1',stdout=log,stderr=subprocess.STDOUT)
    with (OUT/'poller.log').open('w') as log:
        run('docker','exec','-w','/var/www/html',APP,'php','tests/e2e/docker/probes/probe_poller_ack.php',stdout=log,stderr=subprocess.STDOUT,timeout=600)
    result=json.loads((OUT/'poller.log').read_text())
    if len(result.get('reconnects',[]))!=6 or len(result.get('runs',[]))!=2:
        raise RuntimeError('Full poller probe did not produce complete results')
    result['source_base_revision']=run('git','-C',str(ROOT),'rev-parse','HEAD',capture_output=True).stdout.strip()
    result['production_sha256']={path:hashlib.sha256((ROOT/path).read_bytes()).hexdigest() for path in ['lib/poller.php','poller.php','cacti.sql','install/upgrades/1_2_32.php']}
    result['runtime_image']=args.image
    (OUT/'result.json').write_text(json.dumps(result,indent=2))
    print('PASS', OUT/'poller.log',flush=True)
except Exception:
    if (OUT/'poller.log').exists(): print((OUT/'poller.log').read_text()[-5000:],flush=True)
    else: print((OUT/'install.log').read_text()[-5000:] if (OUT/'install.log').exists() else 'No install log',flush=True)
    raise
finally:
    if started:
        subprocess.run(['docker','stop',*started],capture_output=True,check=False)
