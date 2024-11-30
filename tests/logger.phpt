--TEST--
Logger
--DESCRIPTION--
Verify that logger works for both PHP 5.* and PHP 7+ versions,
usage is similar.

For the "DIGEST-MD5 common mech free" message see
https://bugs.launchpad.net/ubuntu/+source/cyrus-sasl2/+bug/827151
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

openlog('', LOG_CONS | LOG_NOWAIT | LOG_PERROR, LOG_LPR);

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
});

?>
--EXPECTREGEX--
.+  \[\d+\] <Info>: lorem ipsum \{\"foo\":true\}
.+  \[\d+\] <Debug>: dolor sit amet \{\"bar\":1\}(\nDIGEST-MD5 common mech free)?