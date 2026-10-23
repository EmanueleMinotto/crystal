--TEST--
PSR-15 middlewares piped in a request handler
--SKIPIF--
<?php require __DIR__.'/http/skipif.inc'; ?>
--FILE--
<?php

require_once __DIR__.'/../vendor/autoload.php';

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

$mf = require_once(__DIR__.'/../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/';

class TraceMiddleware implements MiddlewareInterface
{
    private $name;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $trace = $request->getAttribute('trace', []);
        $trace[] = $this->name;

        return $handler->handle($request->withAttribute('trace', $trace))
            ->withAddedHeader('X-Trace', $this->name);
    }
}

$pipe = $mf('http')->pipe(
    new TraceMiddleware('first'),
    function (ServerRequestInterface $request, RequestHandlerInterface $handler) {
        return $handler->handle($request->withAttribute('trace', array_merge($request->getAttribute('trace'), ['callable'])));
    },
    new TraceMiddleware('last'),
    function (ServerRequestInterface $request) {
        return new Response(200, [], implode(' > ', $request->getAttribute('trace')));
    }
);

var_dump($pipe instanceof RequestHandlerInterface);

$mf('/', $pipe);

$mf('/', function () use ($pipe, $mf) {
    echo "\n";
    echo $pipe->handle($mf('http')->getRequest())->getHeaderLine('X-Trace');
});

?>
--EXPECT--
bool(true)
first > callable > last
last, first
