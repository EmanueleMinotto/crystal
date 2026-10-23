--TEST--
Logger
--DESCRIPTION--
Verify that logger works correctly.

The default implementation writes to syslog, so the test only checks
that logging completes without errors.
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