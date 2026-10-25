--TEST--
Exceptions in route callbacks rendered as error responses
--FILE--
<?php

ob_start();

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/fail';
$_SERVER['SCRIPT_NAME'] = '/';

$errors = $mf('errors');
var_dump($errors->isEnabled(), $errors->isDebug());
$errors->enable();
var_dump($errors->isEnabled(), $errors->isDebug());

$errors->setReporter(function ($exception) {
    echo 'reported: ' . get_class($exception) . ' ' . $exception->getMessage() . "\n";
});

$mf('/fail', function () {
    echo "discarded output\n";
}, 'GET', 0);

$mf('/fail', $mf('middleware')->wrap(function () {
    echo "discarded too\n";

    throw new RuntimeException('<secret> details');
}, function ($request, $next) {
    return $next();
}), 'GET', 1);

$mf('/fail', function () {
    echo "skipped\n";
}, 'GET', 2);

register_shutdown_function(function () {
    register_shutdown_function(function () {
        register_shutdown_function(function () {
            register_shutdown_function(function () {
                echo "\nstatus: " . http_response_code() . "\n";
            });
        });
    });
});

?>
--EXPECT--
bool(false)
bool(false)
bool(true)
bool(false)
reported: RuntimeException <secret> details
<!doctype html><html><head><meta charset="utf-8"><title>500 Internal Server Error</title><style>body{font-family:sans-serif;margin:2em;color:#222}pre{overflow:auto;background:#f4f4f4;padding:1em}</style></head><body><h1>500 Internal Server Error</h1></body></html>
status: 500
