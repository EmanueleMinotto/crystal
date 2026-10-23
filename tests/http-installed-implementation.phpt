--TEST--
Installed PSR-7 implementations are preferred over the default one
--SKIPIF--
<?php require __DIR__.'/http/skipif.inc'; ?>
--FILE--
<?php

require_once __DIR__.'/../vendor/autoload.php';

use Psr\Http\Message\ServerRequestInterface;

$mf = require_once(__DIR__.'/../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/hello/bob';
$_SERVER['SCRIPT_NAME'] = '/';
$_SERVER['HTTP_HOST'] = 'example.com';

$mf('/hello/(?P<name>\w+)', function (ServerRequestInterface $request) use ($mf) {
    echo get_class($mf('http')->getFactory())."\n";
    echo get_class($request)."\n";

    return $mf('http')->pipe(function (ServerRequestInterface $request) {
        return new Nyholm\Psr7\Response(200, [], 'Hello '.$request->getAttribute('name').'!');
    })->handle($request);
});

?>
--EXPECT--
Nyholm\Psr7\Factory\Psr17Factory
Nyholm\Psr7\ServerRequest
Hello bob!
