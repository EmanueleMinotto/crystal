--TEST--
Advanced logger usage
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

$mf(function () use ($mf) {
    $logger = $mf('logger');

    $logger->addContext('bar', 'test');

    $logger->setImplementation(function ($level, $message, $context) {
        echo sprintf("%s: %s - %s\n", $level, $message, json_encode($context));
    });

    $logger->info('lorem ipsum', array(
        'foo' => true,
    ));
    $logger->debug('dolor {placeholder} amet', array(
        'bar' => 1,
        'placeholder' => 'sit',
    ));
    $logger->emergency('consectetur adipisci elit');
});

?>
--EXPECT--
info: lorem ipsum - {"bar":"test","foo":true}
debug: dolor sit amet - {"bar":1,"placeholder":"sit"}
emergency: consectetur adipisci elit - {"bar":"test"}