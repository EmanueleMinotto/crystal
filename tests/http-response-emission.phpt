--TEST--
PSR-7 responses returned by route callbacks are emitted
--SKIPIF--
<?php require __DIR__.'/http/skipif.inc'; ?>
--FILE--
<?php

require_once __DIR__.'/../vendor/autoload.php';

use Psr\Http\Message\ServerRequestInterface;

$mf = require_once(__DIR__.'/../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/missing';
$_SERVER['SCRIPT_NAME'] = '/';

$mf('/missing', function (ServerRequestInterface $request) use ($mf) {
    $factory = $mf('http')->getFactory();

    return $factory->createResponse(404)
        ->withBody($factory->createStream('Not Found: '.$request->getUri()->getPath()));
});

// legacy callbacks keep working on the same route
$mf('/missing', function () {
    echo "\n";
    var_dump(http_response_code());
});

?>
--EXPECT--
Not Found: /missing
int(404)
