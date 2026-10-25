--TEST--
Middleware limiting the requests
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/users';
$_SERVER['SCRIPT_NAME'] = '/';
$_SERVER['REMOTE_ADDR'] = '203.0.113.1';
$_SERVER['HTTP_ACCEPT'] = 'application/json';

$mf('cache')::setDriver('array');

$middleware = $mf('middleware');
$limiter = $mf('limiter');

$route = $middleware->wrap(function () use ($mf) {
    return $mf('response')->json(['users' => []]);
}, $limiter->middleware(2, 60));

foreach ([1, 2, 3] as $i) {
    $response = $route();
    var_dump($response->status(), $response->header('X-RateLimit-Remaining'), $response->header('Retry-After'));
}

// the 429 response is rendered by the errors service
echo $response->body() . "\n";
var_dump($response->header('X-RateLimit-Limit'), $response->header('Content-Type'));

// other IP addresses have their own counter
$_SERVER['REMOTE_ADDR'] = '203.0.113.2';
var_dump($route()->status());

// custom keys, i.e. the user id
$byUser = $middleware->wrap(function () use ($mf) {
    return $mf('response')->setBody('ok');
}, $limiter->middleware(1, 60, function ($request) {
    return 'user:' . $request->header('X-User');
}));

$_SERVER['HTTP_X_USER'] = '1';
var_dump($byUser()->status(), $byUser()->status());
$_SERVER['HTTP_X_USER'] = '2';
var_dump($byUser()->status());

// without the errors service
$mf('errors', null);
$_SERVER['HTTP_X_USER'] = '1';
$response = $byUser();
var_dump($response->status(), $response->body());

?>
--EXPECT--
int(200)
string(1) "1"
NULL
int(200)
string(1) "0"
NULL
int(429)
string(1) "0"
string(2) "60"
{"error":{"status":429,"title":"Too Many Requests"}}
string(1) "2"
string(16) "application/json"
int(200)
int(200)
int(429)
int(200)
int(429)
string(17) "Too Many Requests"
