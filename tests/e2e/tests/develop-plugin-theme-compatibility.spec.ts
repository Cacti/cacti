/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { resolve } from 'node:path';
import revisions from '../plugin-theme-revisions.json';
import { registerPluginThemeTests, type PluginFixture } from '../plugin-theme-matrix';
const core = resolve(__dirname, '../../..');
const pluginRoot = process.env.CACTI_PLUGIN_FIXTURES || resolve(core, '.theme-plugins');
const plugins = Object.fromEntries(Object.keys(revisions).map((id) => [id, resolve(pluginRoot, id)]));
const metadata: Record<string, PluginFixture> = {
	thold: {
		title: 'Threshold Management · North campus edge routers · CPU load and interface alerts',
		status: '<span class="thold-pill tholdWarning" tabindex="0" role="button">Warning</span>',
		metric: '91%',
		caption: 'CPU load',
		row: 'selectable',
		link: 'linkEditMain',
		add: true,
		selectable: true,
	},
	mactrack: {
		title: 'Mactrack Device Filters · North campus distribution switches · Interface inventory',
		status: '<span class="deviceStatus deviceUp">Up</span>',
		metric: '48 ports',
		caption: 'User ports',
		row: 'selectable odd tableRow',
		link: 'linkEditMain',
		add: true,
		selectable: true,
	},
	syslog: {
		title: 'Syslog Message Filter · North campus edge routers · Current interface and authentication alerts',
		status:
			'<button class="syslogValueFilter syslogSeverity syslogSeverity-warning" data-filter-field="priority" data-filter-value="Warning">Warning</button>',
		metric: 'ifDown Gi1/0/24',
		caption: 'Message',
		row: 'tableRow selectable syslogRow syslog-detail-row logWarning',
		link: 'linkEditMain',
		selectable: true,
	},
	alarmmanager: {
		title: 'Current active conditions · North campus edge routers · Notification processing and deliveries',
		status: '<span>Warning</span>',
		metric: '3 attempts',
		caption: 'Delivery',
		row: 'odd tableRow',
		link: 'linkOverDark',
		wrapper: 'alarmmanagerTableScroll',
	},
	hmib: {
		title: 'Hardware Inventory · North campus edge routers · Host Resources MIB device status',
		status:
			'<a class="hmibStatus hmibStatusRunning hmibStatusFilter" href="#running" data-status="2">Running</a>',
		metric: '0 errors',
		caption: 'Errors',
		row: 'odd tableRow',
		link: 'linkEditMain',
	},
	flowview: {
		title: 'FlowView Filters · North campus traffic · Materialization state and active flow queries',
		status: '<span class="deviceStatus deviceUp">Up to date</span>',
		metric: '128 flows',
		caption: 'Flows',
		row: 'odd tableRow selectable',
		link: 'linkEditMain',
		add: true,
		selectable: true,
	},
	monitor: {
		title: 'Monitored Devices · North campus edge routers · Availability and triggered thresholds',
		status: '<span class="monitorPill monitorPillMuted">None</span>',
		metric: '1.8 ms',
		caption: 'Response',
		row: 'tableRow line1 selectable',
		link: 'pic hyperLink',
		selectable: true,
	},
};

registerPluginThemeTests(metadata, revisions, plugins);
