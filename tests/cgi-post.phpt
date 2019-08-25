--TEST--
Basic CGI usage
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/';
$_POST['name'] = 'bar';

$mf('/', function () {
    echo 'Hello ' . $_POST['name'] . '!';
}, 'POST');

?>
--EXPECT--
Hello bar!