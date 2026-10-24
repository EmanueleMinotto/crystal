--TEST--
Environment variables loaded from a .env file
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

putenv('PRESET=from-environment');

$mf(function () use ($mf) {
    $config = $mf('config');

    $defined = $config->loadEnv(__DIR__.'/fixtures/.env');

    $names = array(
        'APP_NAME', 'APP_ENV', 'BLANK', 'SPACED', 'HASH', 'SINGLE',
        'DOUBLE', 'URL', 'MISSING_REF', 'MULTILINE', 'PRESET',
    );

    foreach ($names as $name) {
        var_dump(getenv($name));
    }

    var_dump($_ENV['APP_NAME'], $_SERVER['APP_ENV']);
    var_dump($config->env('APP_DEBUG'));
    var_dump($config->env('APP_CACHE'));
    var_dump($config->env('APP_SECRET', 'default'));
    var_dump($config->env('APP_EMPTY'));
    var_dump($config->env('NOT_DEFINED', 'default'));
    var_dump($defined['PRESET']);

    $config->loadEnv(__DIR__.'/fixtures/.env', true);

    var_dump(getenv('PRESET'));
});

?>
--EXPECT--
string(7) "crystal"
string(3) "dev"
string(0) ""
string(12) "spaced value"
string(3) "a#b"
string(21) "literal ${APP_NAME}\n"
string(36) "crystal "quoted"	escaped ${APP_NAME}"
string(16) "crystal.dev/path"
string(2) "xy"
string(12) "first
second"
string(16) "from-environment"
string(7) "crystal"
string(3) "dev"
bool(true)
bool(false)
NULL
string(0) ""
string(7) "default"
string(16) "from-environment"
string(9) "from-file"