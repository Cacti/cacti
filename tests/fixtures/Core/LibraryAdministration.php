<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

function library_administration(): void {
	global $form_id;
	$session   = $_SESSION;
	$priorForm = $form_id ?? null;

	try {
		$start = date('Y-m-d H:i:s', time() + 86400);

		foreach ([SCHEDULE_WEEKLY => ['day_of_week' => ['1']], SCHEDULE_MONTHLY => ['month' => ['1', '12'], 'day_of_month' => ['1', '28']], SCHEDULE_MONTHLY_ON_DAY => ['month' => ['1'], 'monthly_week' => ['2'], 'monthly_day' => ['3']]] as $type => $required) {
			foreach ([[], $required] as $fields) {
				unset($_SESSION[SESS_MESSAGES]['sched_err']);
				$result = api_scheduler_augment_save(['name' => 'Schedule fixture', 'enabled' => 'on'], ['sched_type' => (string) $type, 'start_at' => $start, 'recur_every' => '1', 'run_limit' => '0'] + $fields);
				library_expect($result['enabled'], $fields ? 'on' : '', 'Incomplete schedules are disabled; complete schedules remain enabled');
				library_expect(isset($_SESSION[SESS_MESSAGES]['sched_err']), !$fields, 'Only incomplete schedules report a validation error');

				foreach ($fields as $field => $values) {
					library_expect($result[$field], implode(',', $values), 'Schedule stores every selected recurrence value');
				}
				library_expect($result['next_start'], $start, 'Future scheduled start is preserved');
			}
		}
		$dom = library_form_dom(static function (): void {
			draw_edit_form(['config' => ['post_to' => 'host.php', 'form_name' => 'coverage_editor', 'enctype' => 'multipart/form-data'],
				'fields'                => ['identity' => ['method' => 'hidden', 'value' => '17'], 'optional' => ['method' => 'hidden']]]);
		});
		library_expect($dom->evaluate('string(//form/@action)'), 'host.php', 'Generated editor retains its submission destination');
		library_expect($dom->evaluate('string(//form/@method)'), 'post', 'Generated editor submits using POST');
		library_expect($dom->evaluate('string(//form/@enctype)'), 'multipart/form-data', 'Generated upload form retains its encoding');
		library_expect($dom->evaluate('string(//input[@name="identity"]/@value)'), '17', 'Generated editor preserves record identity');
		library_expect($dom->evaluate('string(//input[@name="optional"]/@value)'), '', 'Optional hidden field without a default remains empty');

		foreach ([CACTI_PATH_BASE => 'Directory Found'] as $path => $hint) {
			$dom = library_form_dom(static function () use ($path): void { form_dirpath_box('coverage_path', $path, '', 255); });
			library_expect($dom->evaluate('string(//input/@value)'), $path, 'Path feedback preserves the configured destination');
			library_expect($dom->evaluate('string(//span/@title)'), $hint, 'Path feedback distinguishes a directory from a regular file');
		}
		$form_id = 'coverage_editor';
		$dom     = library_form_dom(static function (): void {
			form_save_buttons([['id' => 'apply', 'value' => 'Apply <changes>', 'type' => 'submit'], ['id' => 'back', 'value' => 'Return', 'method' => 'return']], 'host.php', 'create');
		});
		library_expect($dom->evaluate('string(//button[@id="apply"]/@type)'), 'submit', 'Configured submission button retains its type');
		library_expect($dom->evaluate('string(//button[@id="apply"]/@form)'), 'coverage_editor', 'Detached save button targets its owning form');
		library_expect($dom->evaluate('string(//button[@id="apply"])'), 'Apply <changes>', 'Button label remains literal text');
		library_expect($dom->query('//changes')->length, 0, 'Button label cannot create a markup element');
		library_expect($dom->evaluate('string(//button[@id="back"]/@type)'), 'button', 'Return control cannot accidentally submit the editor');
		library_expect($dom->evaluate('string(//button[@id="back"]/@onclick)'), 'cactiReturnTo("host.php")', 'Return control retains the configured local destination');
	} finally {
		$_SESSION = $session;
		$form_id  = $priorForm;
	}
}
