--TEST--
Custom not found routes replace the 404 response
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/missing';
$_SERVER['SCRIPT_NAME'] = '/';

$mf("errors")->enable();
$mf('errors')->setRenderer(function ($status, $exception, $request) use ($mf) {
    return $mf('response')->setStatus($status)->setBody($status . ' ' . $request->path());
});

$mf("/", function () { echo "home"; });
$mf($mf("router:not-found"), function () { echo "custom not found"; });

?>
--EXPECT--
custom not found
