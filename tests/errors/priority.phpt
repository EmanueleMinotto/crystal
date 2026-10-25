--TEST--
Routes with a priority are matched before the 404 response
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'DELETE';
$_SERVER['REQUEST_URI'] = '/late';
$_SERVER['SCRIPT_NAME'] = '/';

$mf("errors")->enable();
$mf('errors')->setRenderer(function ($status, $exception, $request) use ($mf) {
    return $mf('response')->setStatus($status)->setBody($status . ' ' . $request->path());
});

$mf("/late", function () { echo "late route"; }, "DELETE", 3);

?>
--EXPECT--
late route
