--TEST--
Route callbacks returning responses
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/hello';
$_SERVER['SCRIPT_NAME'] = '/';

$mf('/hello', function () {
    echo "echo before\n";
}, 'GET', 0);

$mf('/hello', function () use ($mf) {
    echo "output of the callback, ";

    return $mf('response')->setStatus(201)->setBody("response body\n");
}, 'GET', 1);

$mf('/hello', function () use ($mf) {
    echo "skipped\n";

    return $mf('response')->setBody("skipped\n");
}, 'GET', 2);

$mf('/hel(.*)', function () {
    echo "skipped\n";
}, 'GET', 3);

register_shutdown_function(function () {
    register_shutdown_function(function () {
        register_shutdown_function(function () {
            register_shutdown_function(function () {
                register_shutdown_function(function () {
                    echo 'status: ' . http_response_code() . "\n";
                });
            });
        });
    });
});

?>
--EXPECT--
echo before
output of the callback, response body
status: 201
