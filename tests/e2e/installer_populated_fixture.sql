-- Synthetic data for supported 1.2 release schemas. No production data or credentials.
INSERT INTO settings (name, value) VALUES ('installer_fixture_custom_setting', 'preserve-me'), ('auth_method', '4')
  ON DUPLICATE KEY UPDATE value = VALUES(value);
INSERT INTO host_template (id, hash, name) VALUES (700, '70000000000000000000000000000000', 'Installer custom template');
INSERT INTO plugin_config (directory, name, version, status) VALUES ('installer_missing', 'Installer missing plugin', '0.1', 1),
  ('installer_fixture', 'Installer compatible plugin', '0.1', 1);
INSERT INTO host (id, description, hostname, host_template_id, poller_id, disabled)
  VALUES (700, 'Installer custom device', '127.0.0.1', 700, 1, 'on');
INSERT INTO data_local (id, host_id) VALUES (700, 700);
INSERT INTO data_template_data (id, local_data_id, name, name_cache, active, rrd_step, data_source_path)
  VALUES (700, 700, 'Installer custom source', 'Installer custom source', '', 300, '<path_rra>/installer_fixture.rrd');
INSERT INTO data_template_rrd (id, local_data_id, data_source_name, data_source_type_id, rrd_heartbeat, rrd_minimum, rrd_maximum)
  VALUES (700, 700, 'fixture', 1, 600, '0', 'U');
INSERT INTO user_domains (domain_id, domain_name, type, enabled, defdomain, user_id)
  VALUES (7, 'Installer LDAP', 1, 'on', 0, 0);
INSERT INTO user_domains_ldap (domain_id, server, port, port_ssl, proto_version, encryption,
  referrals, mode, dn, group_require, group_dn, group_attrib, group_member_type,
  search_base, search_filter, specific_dn, specific_password)
  VALUES (7, 'ldap', 389, 636, 3, 0, 0, 0, 'cn=<username>,ou=users,dc=example,dc=org',
    '', 'cn=unused,dc=example,dc=org', 'member', 0, 'ou=users,dc=example,dc=org', '(cn=<username>)', '', '');
INSERT INTO user_auth (id, username, password, realm, full_name, enabled)
  VALUES (700, 'ldapuser', '', 1007, 'Installer LDAP user', 'on');
INSERT INTO user_auth_realm (realm_id, user_id) VALUES (8, 700), (26, 700);
INSERT INTO poller (id, name, hostname, dbhost, dbdefault, dbuser, dbpass, status, last_update)
  VALUES (2, 'Installer collector two', 'collector-two', 'db', 'collector_two', 'root', 'testroot', 2, NOW()),
    (3, 'Installer collector three', 'collector-three', 'db', 'collector_three', 'root', 'testroot', 2, NOW());
INSERT INTO poller_output_boost (local_data_id, rrd_name, time, output)
  SELECT 700, 'fixture', DATE_SUB(NOW(), INTERVAL (e.n * 10000 + a.n * 1000 + b.n * 100 + c.n * 10 + d.n) * 300 SECOND), '42'
  FROM (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) a
  CROSS JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) b
  CROSS JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) c
  CROSS JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) d
  CROSS JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) e;
