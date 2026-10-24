--TEST--
Config and environment loading errors
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$mf(function () use ($mf) {
    $config = $mf('config');

    $sources = array(
        __DIR__.'/fixtures/not-existing.php',
        __DIR__.'/fixtures/config.yml',
        __DIR__.'/fixtures/invalid.json',
        __DIR__.'/fixtures/scalar.php',
        42,
    );

    foreach ($sources as $source) {
        try {
            $config->load($source);
            echo "no exception\n";
        } catch (Exception $e) {
            echo get_class($e).': '.str_replace(__DIR__, '', $e->getMessage())."\n";
        }
    }

    foreach (array('not-existing.env', 'invalid.env', 'unclosed.env') as $file) {
        try {
            $config->loadEnv(__DIR__.'/fixtures/'.$file);
            echo "no exception\n";
        } catch (Exception $e) {
            echo get_class($e).': '.str_replace(__DIR__, '', $e->getMessage())."\n";
        }
    }
});

?>
--EXPECT--
RuntimeException: Configuration file not readable: /fixtures/not-existing.php
InvalidArgumentException: Unsupported configuration file: /fixtures/config.yml
RuntimeException: Invalid JSON in /fixtures/invalid.json: Syntax error
InvalidArgumentException: Configuration file must contain an array: /fixtures/scalar.php
InvalidArgumentException: Configuration source must be an array or a file path
RuntimeException: Environment file not readable: /fixtures/not-existing.env
InvalidArgumentException: Invalid line 2 in /fixtures/invalid.env
InvalidArgumentException: Unclosed quote at line 2 in /fixtures/unclosed.env