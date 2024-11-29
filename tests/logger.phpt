--TEST--
Logger
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

openlog('', LOG_CONS | LOG_NOWAIT | LOG_PERROR, LOG_LPR);

$mf(function () use ($mf) {
    $logger = $mf('logger');

    $logger('info', 'lorem ipsum', array(
        'foo' => true,
    ));
    $logger('debug', 'dolor sit amet', array(
        'bar' => 1,
    ));
});

?>
--EXPECTREGEX--
.+  \[\d+\] <Info>: lorem ipsum \{\"foo\":true\}
.+  \[\d+\] <Debug>: dolor sit amet \{\"bar\":1\}