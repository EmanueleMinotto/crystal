--TEST--
PSR logger when PHP 7.0+
--SKIPIF--
<?php

$message = 'Skip: psr/log required';

if (!file_exists(__DIR__.'/../vendor/autoload.php')) {
    die($message);
}

include_once __DIR__.'/../vendor/autoload.php';

if (!class_exists('Psr\Log\AbstractLogger')) {
    die($message);
}

?>
--FILE--
<?php

require_once __DIR__.'/../vendor/autoload.php';

$mf = require_once(__DIR__.'/../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/';

$internalLogger = new class() extends \Psr\Log\AbstractLogger {
    public function log($level, $message, array $context = array())
    {
        echo $level.': '.$message.'.';
    }
};

$mf('/', function () use (&$mf, $internalLogger) {
    $mf('logger')->setLogger($internalLogger);

    $mf('logger')->info('h1');
});

?>
--EXPECT--
info: h1.