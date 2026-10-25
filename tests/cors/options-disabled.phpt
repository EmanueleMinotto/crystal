--TEST--
OPTIONS requests matching only the path of routes receive a 405 response when CORS isn't enabled
--FILE--
<?php

ob_start();

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'OPTIONS';
$_SERVER['REQUEST_URI'] = '/posts';
$_SERVER['SCRIPT_NAME'] = '/';

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
error 405
int(405)
