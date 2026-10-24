--TEST--
CGI callbacks returning values
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/users/1';
$_SERVER['SCRIPT_NAME'] = '/';

$mf('/users/(?P<id>\d+)', function ($id) {
    return array('id' => (int) $id, 'name' => 'Bob');
});

$mf('/users/(\d+)', function () use ($mf) {
    return $mf('response')->text(PHP_EOL.'text response');
});

$mf('/users/.*', function () {
    echo PHP_EOL.'echo and ';

    return 'string';
});

?>
--EXPECT--
{"id":1,"name":"Bob"}
text response
echo and string
