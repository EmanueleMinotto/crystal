--TEST--
PSR-15 request handlers used as route callbacks
--SKIPIF--
<?php require __DIR__.'/http/skipif.inc'; ?>
--FILE--
<?php

require_once __DIR__.'/../vendor/autoload.php';

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

$mf = require_once(__DIR__.'/../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/hello/bob';
$_SERVER['SCRIPT_NAME'] = '/';
$_POST = ['greeting' => 'Hello'];

class HelloHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new Response(200, [], $request->getParsedBody()['greeting'].' '.$request->getAttribute('name').'!');
    }
}

$mf('/hello/(?P<name>\w+)', new HelloHandler(), 'POST');

?>
--EXPECT--
Hello bob!
