--TEST--
Arrays returned by route callbacks are JSON responses
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/users';
$_SERVER['SCRIPT_NAME'] = '/';

$headers = function ($request, callable $next) {
    $response = $next();

    echo $response->status().' '.$response->header('Content-Type')."\n";

    return $response;
};

// with middlewares too
$mf('/users', $mf('middleware')->wrap(function () {
    return ['users' => ['Alice', 'Bob']];
}, $headers), 'GET', 0);

// the first response is sent, the next callbacks are skipped
$mf('/users', function () {
    echo "skipped\n";
}, 'GET', 1);

?>
--EXPECT--
200 application/json
{"users":["Alice","Bob"]}
