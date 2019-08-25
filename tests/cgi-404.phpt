--TEST--
404 CGI usage
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/test';
$_SERVER['SCRIPT_NAME'] = '/';

$mf('/', function () {
    echo 'Hello World!';
});

$mf($mf('route_not_found'), function () {
    echo 'Error 404: Page not Found';
});

?>
--EXPECT--
Error 404: Page not Found