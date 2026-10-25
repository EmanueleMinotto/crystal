--TEST--
Without the errors service enabled there are no 404 responses
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/missing';
$_SERVER['SCRIPT_NAME'] = '/';

register_shutdown_function(function () { register_shutdown_function(function () { echo "nothing"; }); });
$mf('errors')->setRenderer(function ($status, $exception, $request) use ($mf) {
    return $mf('response')->setStatus($status)->setBody($status . ' ' . $request->path());
});

$mf("/", function () { echo "home"; });

?>
--EXPECT--
nothing
