--TEST--
PSR-7 request injected in route callbacks
--SKIPIF--
<?php require __DIR__.'/http/skipif.inc'; ?>
--FILE--
<?php

require_once __DIR__.'/../vendor/autoload.php';

use Psr\Http\Message\ServerRequestInterface;

$mf = require_once(__DIR__.'/../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/users/42/posts/7?page=2';
$_SERVER['SCRIPT_NAME'] = '/';
$_SERVER['HTTP_HOST'] = 'example.com';
$_SERVER['HTTP_X_CUSTOM_HEADER'] = 'foo';
$_GET = ['page' => '2'];

$mf('/users/(?P<user>\d+)/posts/(\d+)', function ($post, ServerRequestInterface $request, $user) {
    echo $request->getMethod().' '.$request->getUri()."\n";
    echo $request->getHeaderLine('X-Custom-Header')."\n";
    echo $request->getQueryParams()['page']."\n";
    echo $request->getAttribute('user')."\n";
    echo $user.' '.$post."\n";
});

?>
--EXPECT--
GET http://example.com/users/42/posts/7?page=2
foo
2
42
42 7
