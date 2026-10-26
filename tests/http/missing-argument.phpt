--TEST--
Route callbacks with parameters that are neither route values nor services
--SKIPIF--
<?php
if (PHP_VERSION_ID < 70100) {
    die('Skip: ArgumentCountError is available from PHP 7.1');
}
?>
--FILE--
<?php

ob_start();

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/posts/42?page=2';
$_SERVER['SCRIPT_NAME'] = '/';

$mf('errors')->enable();
$mf('errors')->setReporter(function ($exception) {
    echo get_class($exception).': '.$exception->getMessage()."\n";
});

// $id is a value of the route, $request is injected, $missing is neither
$mf->get('/posts/([0-9]+)', function ($id, $request, $missing) {
    echo "not invoked\n";
});

register_shutdown_function(function () {
    register_shutdown_function(function () {
        register_shutdown_function(function () {
            echo "\nstatus: ".http_response_code()."\n";
        });
    });
});

?>
--EXPECTF--
ArgumentCountError: Too few arguments to the callback of the route GET /posts/42: $missing is neither a value of the route nor a service.
%A
status: 500
