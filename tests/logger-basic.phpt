--TEST--
Logger
--DESCRIPTION--
Verify that logger works for both PHP 5.* and PHP 7+ versions,
usage is similar.

PHPUnit 4.8 does not accept the EXPECTREGEX section, so the check
must be very basic.
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

$mf(function () use ($mf) {
    $logger = $mf('logger');

    if (PHP_MAJOR_VERSION < 7) {
        $logger('info', 'lorem ipsum', array(
            'foo' => true,
        ));
        $logger('debug', 'dolor {placeholder} amet', array(
            'bar' => 1,
            'placeholder' => 'sit',
        ));
    } else {
        $logger->info('lorem ipsum', array(
            'foo' => true,
        ));
        $logger->debug('dolor sit {placeholder}', array(
            'bar' => 1,
            'placeholder' => 'amet',
        ));
    }

    echo "completed correctly";
});

?>
--EXPECT--
completed correctly