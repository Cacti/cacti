<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

function __($text, ...$args) {
	return $args ? sprintf($text, ...$args) : $text;
}

function plugin_message_failure_version() {
	return ['longname' => 'Message fixture', 'author' => 'Cacti tests', 'version' => '1.0'];
}
function plugin_message_no_install_version() {
	return plugin_message_failure_version();
}
function plugin_message_failure_install() {
	return false;
}
