--TEST--
Requests matching only the path of routes receive a 405 response
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'DELETE';
$_SERVER['REQUEST_URI'] = '/posts';
$_SERVER['SCRIPT_NAME'] = '/';

$mf("errors")->enable();
$mf('errors')->setRenderer(function ($status, $exception, $request) use ($mf) {
    return $mf('response')->setStatus($status)->setBody($status . ' ' . $request->path());
});

$mf("/posts", function () { echo "list"; }, "GET|post");
$mf("/posts", function () { echo "update"; }, "PUT", 1);
$mf("/p(.*)", function () { echo "patch"; }, "PATCH|(OPTIONS)");

?>
--EXPECT--
405 /posts
