<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 */

/**
 * Extract the final HTTP response status from stream-wrapper headers.
 *
 * @param array<int, string> $headers Response header lines
 *
 * @return int|null Final HTTP status, or null when no valid status is present
 */
function remote_agent_http_status(array $headers) : ?int {
	$status = null;

	foreach ($headers as $header) {
		if (preg_match('/^HTTP\/\S+\s+([1-5][0-9]{2})(?:\s|$)/i', $header, $matches)) {
			$status = (int) $matches[1];
		}
	}

	return $status;
}

/**
 * Build bounded, non-redirecting stream options for Remote Agent requests.
 *
 * @param string $protocol    Request protocol
 * @param int    $timeout     Read timeout in seconds
 * @param bool   $verify_peer Whether TLS certificates and names are verified
 * @param string $ca_file     Optional PEM CA bundle
 * @param string $peer_name   Expected TLS certificate hostname
 *
 * @return array<string, array<string, mixed>> Stream context options
 */
function remote_agent_context_options(string $protocol, int $timeout, bool $verify_peer, string $ca_file = '', string $peer_name = '') : array {
	$options = [];

	if (in_array($protocol, ['ssl', 'https', 'ftps'], true)) {
		$options['ssl'] = [
			'verify_peer'       => $verify_peer,
			'verify_peer_name'  => $verify_peer,
			'allow_self_signed' => !$verify_peer,
			'SNI_enabled'       => true
		];

		if ($peer_name !== '') {
			$options['ssl']['peer_name'] = trim($peer_name, '[]');
		}

		if ($ca_file !== '') {
			$options['ssl']['cafile'] = $ca_file;
		}
	}

	if ($protocol === 'https' || $protocol === 'http') {
		$options['http'] = [
			'timeout'         => $timeout,
			'ignore_errors'   => true,
			'follow_location' => 0,
			'max_redirects'   => 0,
			'header'          => "Accept: application/json, image/*;q=0.9, text/plain;q=0.5\r\nConnection: close\r\n"
		];
	}

	return $options;
}

/**
 * Validate a JSON graph envelope returned by a Remote Agent.
 *
 * @param string $response Complete response body
 *
 * @return array<string, mixed>|false Validated envelope, or false
 */
function remote_graph_json_envelope(string $response) : array|false {
	try {
		$decoded = json_decode($response, true, 32, JSON_THROW_ON_ERROR);
	} catch (JsonException $e) {
		return false;
	}

	if (!is_array($decoded) || !isset($decoded['image']) || !is_string($decoded['image']) || $decoded['image'] === '') {
		return false;
	}

	$image = base64_decode($decoded['image'], true);

	if (!is_string($image) || $image === '') {
		return false;
	}

	$is_png = str_starts_with($image, "\x89PNG\r\n\x1a\n");
	$is_gif = str_starts_with($image, 'GIF87a') || str_starts_with($image, 'GIF89a');
	$is_svg = preg_match('/\A(?:\xEF\xBB\xBF)?\s*(?:<\?xml[^>]*\?>\s*)?<svg(?:\s|>)/i', $image) === 1;

	return $is_png || $is_gif || $is_svg ? $decoded : false;
}
