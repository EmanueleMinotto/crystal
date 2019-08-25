--TEST--
CGI with named subpatterns
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/bob/hello';
$_SERVER['SCRIPT_NAME'] = '/';

$mf('/(?P<user>[^\/]+)/(?P<verb>[^\/]+)', function ($verb, $user) {
    echo 'Output: ' . $verb . ' ' . $user . '!';
});

?>
--EXPECT--
Output: hello bob!