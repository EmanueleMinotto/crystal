--TEST--
PSR-15 request handlers used as route callbacks
--FILE--
<?php

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
    private $factory;

    public function __construct($factory)
    {
        $this->factory = $factory;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->factory->createResponse()->withBody($this->factory->createStream(
            $request->getParsedBody()['greeting'].' '.$request->getAttribute('name').'!'
        ));
    }
}

$mf('/hello/(?P<name>\w+)', new HelloHandler($mf('http')->getFactory()), 'POST');

?>
--EXPECT--
Hello bob!
