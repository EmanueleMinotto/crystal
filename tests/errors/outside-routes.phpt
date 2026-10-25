--TEST--
Exceptions thrown outside the route callbacks
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/';
$_SERVER['HTTP_ACCEPT'] = 'application/json';

$mf('errors')->enable();
$mf('errors')->setReporter(function ($exception) {
    echo 'reported: ' . $exception->getMessage() . "\n";
});

// routes aren't invoked after the error response
$mf('/', function () {
    echo "not invoked\n";
});

throw new Exception('bootstrap failed');

?>
--EXPECT--
reported: bootstrap failed
{"error":{"status":500,"title":"Internal Server Error"}}
