--TEST--
PSR-7 and PSR-15 interfaces polyfilled only when requested
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

$interfaces = [
    'Psr\Http\Message\ServerRequestInterface',
    'Psr\Http\Message\ResponseInterface',
    'Psr\Http\Server\MiddlewareInterface',
];

foreach ($interfaces as $interface) {
    var_dump(interface_exists($interface, false));
}

foreach ($interfaces as $interface) {
    var_dump(interface_exists($interface));
}

var_dump(interface_exists('Psr\Http\Message\MessageInterface', false));
var_dump(interface_exists('Psr\Http\Message\Unknown'));

?>
--EXPECT--
bool(false)
bool(false)
bool(false)
bool(true)
bool(true)
bool(true)
bool(true)
bool(false)
