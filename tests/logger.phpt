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
        $logger('debug', 'dolor sit amet', array(
            'bar' => 1,
        ));
    } else {
        $logger->info('lorem ipsum', array(
            'foo' => true,
        ));
        $logger->debug('dolor sit amet', array(
            'bar' => 1,
        ));
    }

    echo "completed correctly";
});

?>
--EXPECT--
completed correctly