<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

include __DIR__ . '/../include/db_functions.php';
include __DIR__ . '/../include/arrays.php';
include  '../../include/global.php';

$client_ip = get_client_addr();

$app = AppFactory::create();

$app->addErrorMiddleware(true, true, true);

$app->get('/', function (Request $request, Response $response) {
	$response->getBody()->write('Welcome to the Cacti API!');

	return $response;
});

/**
 * Validates that all provided parameters are within the allowed parameter list.
 *
 * This function checks each parameter key against a list of allowed parameters
 * and returns an error message if any invalid parameters are found.
 *
 * @param  array       $params         The parameters to validate (key-value pairs)
 * @param  array       $allowed_params Array of allowed parameter names
 * @return string|null Returns an error message if invalid parameters are found, otherwise null
 */
function validate_parameters(array $params, array $allowed_params) : string|null {
	foreach ($params as $key => $value) {
		if (!in_array($key, $allowed_params, true)) {
			return 'ERROR: Invalid parameter passed: "' . htmlspecialchars($key) . '"';
		}
	}

	return null;
}

// V1 API Routes Group
$app->group('/v1', function (RouteCollectorProxy $group) {
	// Info endpoints
	$group->group('/info', function (RouteCollectorProxy $infoGroup) {
		$infoGroup->get('/hosts', function (Request $request, Response $response) {
			global $allowed_hosts_filter, $client_ip;

			$params = $request->getQueryParams();
			$verror = validate_parameters($params, $allowed_hosts_filter);

			if ($verror) {
				$response->getBody()->write(json_encode($verror));

				cacti_log($verror . ' By HOST: ' . $client_ip, false, 'API');

				return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
			}

			$json = json_encode(get_hosts($params));

			$response->getBody()->write($json);

			return $response->withHeader('Content-Type', 'application/json');
		});

		$infoGroup->get('/host_templates', function (Request $request, Response $response) {
			global $allowed_host_templates_filter, $client_ip;

			$params = $request->getQueryParams();
			$verror = validate_parameters($params, $allowed_host_templates_filter);

			if ($verror) {
				$response->getBody()->write(json_encode($verror));

				cacti_log($verror . ' By HOST: ' . $client_ip, false, 'API');

				return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
			}

			$json = json_encode(get_host_templates($params['template_id'] ?? 0));

			$response->getBody()->write($json);

			return $response->withHeader('Content-Type', 'application/json');
		});

		$infoGroup->get('/graph_list', function (Request $request, Response $response) {
			$params  = $request->getQueryParams();
			$host_id = $params['host_id'] ?? 0;
			$json    = json_encode(get_graph_list($host_id));

			$response->getBody()->write($json);

			return $response->withHeader('Content-Type', 'application/json');
		});

		$infoGroup->get('/automation_networks', function (Request $request, Response $response) {
			global $allowed_automation_networks_filter, $client_ip;

			$params = $request->getQueryParams();
			$verror = validate_parameters($params, $allowed_automation_networks_filter);

			if ($verror) {
				$response->getBody()->write(json_encode($verror));

				cacti_log($verror . ' By HOST: ' . $client_ip, false, 'API');

				return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
			}

			$json = json_encode(get_automation_networks($params));

			$response->getBody()->write($json);

			return $response->withHeader('Content-Type', 'application/json');
		});
	});

	// Status endpoints
	$group->group('/status', function (RouteCollectorProxy $statusGroup) {
		$statusGroup->get('/poller_status', function (Request $request, Response $response) {
			$params    = $request->getQueryParams();
			$poller_id = $params['poller_id'] ?? 0;
			$hosts     = get_poller_status($poller_id);

			$response->getBody()->write(json_encode($hosts));

			return $response->withHeader('Content-Type', 'application/json');
		});

		$statusGroup->get('/cacti_status', function (Request $request, Response $response) {
			$json = json_encode(get_cacti_status());

			$response->getBody()->write($json);

			return $response->withHeader('Content-Type', 'application/json');
		});

		$statusGroup->get('/cacti_db_status', function (Request $request, Response $response) {
			$json = json_encode(get_cacti_db_status());

			$response->getBody()->write($json);

			return $response->withHeader('Content-Type', 'application/json');
		});

		$statusGroup->get('/boost_status', function (Request $request, Response $response) {
			$json = json_encode(get_boost_status());

			$response->getBody()->write($json);

			return $response->withHeader('Content-Type', 'application/json');
		});

		$statusGroup->get('/dsstats', function (Request $request, Response $response) {
			$json = json_encode(get_dsstats_status());

			$response->getBody()->write($json);

			return $response->withHeader('Content-Type', 'application/json');
		});

		$statusGroup->get('/api_db_ping', function (Request $request, Response $response) {
			$ping_result = db_fetch_row('SELECT 1 from version');

			if (!$ping_result) {
				$response->getBody()->write(json_encode(['error' => 'Database connection failed']));

				return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
			}

			$response->getBody()->write(json_encode(['status' => 'Database connection test successful']));

			return $response->withHeader('Content-Type', 'application/json');
		});

		// automation status endpoint
		$statusGroup->get('/automation', function (Request $request, Response $response) {
			$json = json_encode(get_automation_status());

			$response->getBody()->write($json);

			return $response->withHeader('Content-Type', 'application/json');
		});
	});

	// Plugin endpoints
	$group->group('/plugin', function (RouteCollectorProxy $pluginGroup) {
		$pluginGroup->group('/thold', function (RouteCollectorProxy $tholdGroup) {
			$tholdGroup->get('/thresholds', function (Request $request, Response $response) {
				global $allowed_thold_filter, $client_ip;

				$params = $request->getQueryParams();
				$verror = validate_parameters($params, $allowed_thold_filter);

				if ($verror) {
					$response->getBody()->write(json_encode($verror));

					cacti_log($verror . ' By HOST: ' . $client_ip, false, 'API');

					return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
				}

				$json = json_encode(get_thresholds($params));

				$response->getBody()->write($json);

				return $response->withHeader('Content-Type', 'application/json');
			});

			$tholdGroup->get('/status', function (Request $request, Response $response) {
				$json = json_encode(get_threshold_status());

				$response->getBody()->write($json);

				return $response->withHeader('Content-Type', 'application/json');
			});
		});
	});
});

$app->run();
