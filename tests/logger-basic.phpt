--TEST--
Logger
--SKIPIF--
<?php if (PHP_MAJOR_VERSION < 7) die('Skip: PHP 7+ is required'); ?>
--DESCRIPTION--
Verify that logger works correctly.

PHPUnit 4.8 does not accept the EXPECTREGEX section, so the check
must be very basic.
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

$mf(function () use ($mf) {
    $logger = $mf('logger');

    $logger->info('lorem ipsum', array(
        'foo' => true,
    ));
    $logger->debug('dolor sit {placeholder}', array(
        'bar' => 1,
        'placeholder' => 'amet',
    ));

    echo "completed correctly";
});

?>
--EXPECT--
completed correctly