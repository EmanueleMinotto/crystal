--TEST--
Values of the route parameters
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/compare/5/5';
$_SERVER['SCRIPT_NAME'] = '/';

// parameters with the same value
$mf('/compare/(?P<a>[0-9]+)/(?P<b>[0-9]+)', function ($a, $b) {
    var_dump($a, $b);
}, 'GET', 0);

$mf('/compare/([0-9]+)/([0-9]+)', function ($a, $b) {
    var_dump($a, $b);
}, 'GET', 1);

// numeric values are converted by PHP, empty values use the default value
$mf('/compare/(?P<a>[0-9]+)/(?P<b>[0-9]+)(?P<page>[0-9]*)', function (int $a, float $b, int $page = 1) {
    var_dump($a, $b, $page);
}, 'GET', 2);

?>
--EXPECT--
string(1) "5"
string(1) "5"
string(1) "5"
string(1) "5"
int(5)
float(5)
int(1)
