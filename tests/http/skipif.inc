<?php

require_once __DIR__.'/../../vendor/autoload.php';

if (!class_exists('Nyholm\Psr7\Factory\Psr17Factory') || !interface_exists('Psr\Http\Server\MiddlewareInterface')) {
    die('skip nyholm/psr7 and psr/http-server-middleware are required');
}
