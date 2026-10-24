--TEST--
CGI method not allowed route
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/users';
$_SERVER['SCRIPT_NAME'] = '/';

$mf('/users', function () {
    echo 'list';
});

$mf($mf('router:method-not-allowed'), function () use ($mf) {
    echo 'Error 405: use '.implode(', ', $mf('router:allowed-methods'));
});

$mf($mf('router:not-found'), function () {
    echo 'Error 404: Page not Found';
});

?>
--EXPECT--
Error 405: use GET
