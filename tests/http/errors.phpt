--TEST--
HTTP errors rendered as Problem Details
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$mf->testing();

$logs = array();
$mf('logger')->setImplementation(function ($level, $message) use (&$logs) {
    $logs[] = $level.': '.$message;
});

$mf->get('/abort', function () use ($mf) {
    $mf('errors')->abort(409, 'Order already paid.', array('order' => 7), array('X-Order' => '7'));
});
$mf->get('/crash', function () {
    echo 'partial output';

    throw new LogicException('Database password is wrong');
});
$mf->get('/custom', function () {
    throw new DomainException('custom');
});

$show = function ($response) {
    echo $response->getStatusCode().' '.$response->getHeaderLine('Content-Type').' '.$response->getBody().PHP_EOL;
};

$response = $mf->handle('GET', '/abort');
$show($response);
echo $response->getHeaderLine('X-Order').PHP_EOL;

// unknown exceptions don't leak details and are logged
$show($mf->handle('GET', '/crash'));
echo count($logs).' '.strtok($logs[0], "\n").PHP_EOL;

// browsers receive an HTML page
$show($mf->handle('GET', '/missing', array('headers' => array('Accept' => 'text/html,application/xhtml+xml'))));

$errors = $mf('errors');

$errors->setDebug(true);
$body = $mf->handle('GET', '/crash')->json();
echo $body['detail'].' '.$body['exception']['class'].PHP_EOL;
$errors->setDebug(false);

$errors->setFormat('html');
echo $mf->handle('GET', '/missing')->getHeaderLine('Content-Type').PHP_EOL;
$errors->setFormat('auto');

$errors->setHandler(function ($exception) use ($mf) {
    if ($exception instanceof DomainException) {
        return $mf('response')->text('handled '.$exception->getMessage(), 418);
    }
});
$show($mf->handle('GET', '/custom'));
echo $mf->handle('GET', '/abort')->getStatusCode().PHP_EOL;
$errors->setHandler(null);

$exception = $errors->create(404, 'Missing');
var_dump($exception instanceof RuntimeException, $exception->getStatusCode(), $exception->getMessage());

?>
--EXPECTF--
409 application/problem+json {"type":"about:blank","title":"Conflict","status":409,"detail":"Order already paid.","order":7}
7
500 application/problem+json {"type":"about:blank","title":"Internal Server Error","status":500}
1 error: Uncaught LogicException: Database password is wrong in %s:%d
404 text/html; charset=UTF-8 <!DOCTYPE html><html><head><meta charset="UTF-8"><title>404 Not Found</title></head><body><h1>404 Not Found</h1></body></html>
Database password is wrong LogicException
text/html; charset=UTF-8
418 text/plain; charset=UTF-8 handled custom
409
bool(true)
int(404)
string(7) "Missing"
