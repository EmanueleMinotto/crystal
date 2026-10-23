--TEST--
Services persist between calls
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

$mf(function () use ($mf) {
    foreach (['cache', 'container', 'database', 'listener', 'logger', 'template'] as $service) {
        echo $service.': ';
        var_dump($mf($service) === $mf($service));
    }

    $mf('listener')->on('ping', function () {
        echo "pong\n";
    });
    $mf('listener')->dispatch('ping');
});

?>
--EXPECT--
cache: bool(true)
container: bool(true)
database: bool(true)
listener: bool(true)
logger: bool(true)
template: bool(true)
pong
