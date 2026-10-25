--TEST--
OPTIONS requests matching only the path of routes receive a 204 response when CORS is enabled
--FILE--
<?php

ob_start();

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'OPTIONS';
$_SERVER['REQUEST_URI'] = '/posts';
$_SERVER['SCRIPT_NAME'] = '/';

$router = $mf('router');

$response = $router->options(['GET|post', 'PUT', '(PATCH)', 'OPTIONS']);
var_dump($response->status(), $response->header('Allow'), $response->body());

$response = $router->options(['(GET)']);
var_dump($response->status(), $response->header('Allow'));

// answered before the errors service sends a 405 response
$mf('cors')->enable(['origins' => ['https://app.example.com']]);
$mf('errors')->enable();
$mf('errors')->setRenderer(function ($status) use ($mf) {
    return $mf('response')->setStatus($status)->setBody("error $status\n");
});

$mf('/posts', function () { echo "list"; }, 'GET|POST');
$mf('/posts', function () { echo "update"; }, 'PUT', 1);
$mf('/other', function () { echo "other"; }, 'GET');

// after the routes with a priority
register_shutdown_function(function () {
    register_shutdown_function(function () {
        register_shutdown_function(function () {
            var_dump(http_response_code());
        });
    });
});

?>
--EXPECT--
int(204)
string(23) "GET, POST, PUT, OPTIONS"
string(0) ""
int(204)
NULL
int(204)
