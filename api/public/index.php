<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;


include __DIR__ . '/../include/db_functions.php';
include __DIR__ . '/../include/arrays.php';
require __DIR__ . '/../vendor/autoload.php';

// Instantiate App
$app = AppFactory::create();

// Add error middleware
$app->addErrorMiddleware(true, true, true);


$app->get("/", function (Request $request, Response $response) {
    $response->getBody()->write("Welcome to the Cacti API!");
    return $response;
});


$app->get("/info/hosts", function (Request $request, Response $response) {
    global $allowed_hosts_filter;
    $params = $request->getQueryParams();
    foreach($params as $key => $value) {
        if (!in_array($key, $allowed_hosts_filter)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid parameter: ' . $key]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
    }
    $json = json_encode(get_hosts($params));
    $response->getBody()->write($json);
    return $response->withHeader('Content-Type', 'application/json');
});



$app->get("/info/host_templates", function (Request $request, Response $response) {
    global $allowed_host_templates_filter;
    $params = $request->getQueryParams();
    foreach($params as $key => $value) {
        if (!in_array($key, $allowed_host_templates_filter)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid parameter: ' . $key]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
    }
    $json = json_encode(get_host_templates($params['template_id'] ?? 0));
    $response->getBody()->write($json);
    return $response->withHeader('Content-Type', 'application/json');
});


$app->get("/info/graph_list", function (Request $request, Response $response) {
    $params = $request->getQueryParams();
    $host_id = $params['host_id'] ?? 0;
    $json = json_encode(get_graph_list($host_id));
    $response->getBody()->write($json);
    return $response->withHeader('Content-Type', 'application/json');
});




//Core cacti status endpoints

$app->get("/status/poller_status", function (Request $request, Response $response) {
    $params = $request->getQueryParams();
    $poller_id = $params['poller_id'] ?? 0;
    $hosts = get_poller_status($poller_id);
    $json = json_encode($hosts);
    $response->getBody()->write($json);
    return $response->withHeader('Content-Type', 'application/json');
});




$app->get("/status/cacti_status", function (Request $request, Response $response) {
    $json = json_encode(get_cacti_status());
    $response->getBody()->write($json);
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get("/status/cacti_db_status", function (Request $request, Response $response) {
    $json = json_encode(get_cacti_db_status());
    $response->getBody()->write($json);
    return $response->withHeader('Content-Type', 'application/json');
});


$app->get("/status/boost_status", function (Request $request, Response $response) {
    $json = json_encode(get_boost_status());
    $response->getBody()->write($json);
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get("/status/api_db_ping", function (Request $request, Response $response) {
    $ping_result = db_fetch_row("SELECT 1 from version");
    if (!$ping_result) {
        $response->getBody()->write(json_encode(['error' => 'Database connection failed']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
    $response->getBody()->write(json_encode(['status' => 'Database connection test successful']));
    return $response->withHeader('Content-Type', 'application/json');
});




//Plugin Endpoints

$app->get('/plugin/thold/thresholds', function (Request $request, Response $response) {
    global $allowed_thold_filter;
    $params = $request->getQueryParams();
    foreach ($params as $key => $value) {
        if (!in_array($key, $allowed_thold_filter)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid parameter: ' . $key]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
    }
    $json = json_encode(get_thresholds($params));
    $response->getBody()->write($json);
    return $response->withHeader('Content-Type', 'application/json');
});


$app->get('/plugin/thold/status', function (Request $request, Response $response) {
    $json = json_encode(get_threshold_status());
    $response->getBody()->write($json);
    return $response->withHeader('Content-Type', 'application/json');
});




$app->run();