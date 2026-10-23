"""Validate selection SQL semantics with SQLite, not MariaDB engine behavior."""
from pathlib import Path
import sqlite3

root = Path(__file__).resolve().parents[3]
source = (root / 'lib/poller.php').read_text()
start = source.index("'/* poller output housekeeping */") + 1
end = source.index("', $params);", start)
query = source[start:end].replace("' . $after . '", '')
conn = sqlite3.connect(':memory:')
conn.executescript('''
CREATE TABLE host(id INTEGER PRIMARY KEY, poller_id INTEGER);
CREATE TABLE data_local(id INTEGER PRIMARY KEY, host_id INTEGER, data_template_id INTEGER);
CREATE TABLE data_template(id INTEGER PRIMARY KEY, name TEXT);
CREATE TABLE poller_item(local_data_id INTEGER, rrd_name TEXT, rrd_num INTEGER);
CREATE TABLE poller_output(local_data_id INTEGER, rrd_name TEXT, time TEXT, output TEXT,
 PRIMARY KEY(local_data_id,rrd_name,time));
INSERT INTO host VALUES(1,1),(2,2);
INSERT INTO data_template VALUES(1,'Errors');
''')
fields = ['discards_in','discards_out','errors_in','errors_out']
for ds in [1,2,3,4,5]:
    conn.execute('INSERT INTO data_local VALUES(?,?,1)', (ds,2 if ds == 5 else 1))
    conn.executemany('INSERT INTO poller_item VALUES(?,?,4)', [(ds,f) for f in fields])
    actual = fields if ds in [2,4] else fields[1:]
    # Four fields but one name is not configured: count alone must not pass.
    if ds == 4: actual = ['unknown', *fields[1:]]
    conn.executemany("INSERT INTO poller_output VALUES(?,?,'2026-10-23 10:00:00','0')", [(ds,f) for f in actual])
conn.execute("INSERT INTO poller_output VALUES(99,'v','2026-10-23 10:00:00','0')")
rows = conn.execute(query,(1,)).fetchall()
assert {r[0] for r in rows} == {1,3,4,99}, rows
assert len(rows) == 11, rows
# Keyset pagination uses the same ORDER BY as the compound primary key.
after = ''' AND (po.local_data_id > ? OR (po.local_data_id = ? AND po.rrd_name > ?)
 OR (po.local_data_id = ? AND po.rrd_name = ? AND po.time > ?))'''
paged_query = source[start:end].replace("' . $after . '", after)
last = rows[4]
params=(1,last[0],last[0],last[1],last[0],last[1],last[2])
assert conn.execute(paged_query,params).fetchall() == rows[5:]
print('PASS: actual housekeeping SELECT excludes complete and remote groups, includes orphan/unknown-field groups, and paginates correctly (SQLite semantics only)')
