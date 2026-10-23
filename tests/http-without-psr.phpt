--TEST--
PSR-7 support is opt-in and doesn't require any package
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/';

$mf('/', function () {
    echo "Hello World!\n";

    return 'ignored';
});

$mf('/', function () use ($mf) {
    var_dump(interface_exists('Psr\Http\Message\ServerRequestInterface'));

    try {
        $mf('http')->getRequest();
    } catch (RuntimeException $e) {
        echo $e->getMessage();
    }
});

?>
--EXPECT--
Hello World!
bool(false)
No PSR-17 factory available: install one (e.g. nyholm/psr7) or call setFactory()
