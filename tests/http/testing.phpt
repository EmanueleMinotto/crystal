--TEST--
Testing mode dispatches requests in-process only
--FILE--
<?php

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/';

$mf = require_once(__DIR__.'/../../crystal.php');

$mf->testing();

$mf('/', function () {
    echo 'printed only when handled';
});

$mf->get('/status', function () use ($mf) {
    echo 'accepted';

    return $mf('response')->create()->withStatus(202);
});

$mf->get('/priorities', function () {
    return 'B';
}, array('priority' => 0));
$mf->get('/priorities', function () {
    return 'A';
}, array('priority' => 1));

$response = $mf->handle('GET', '/');
echo $response->getStatusCode().' '.$response->getBody().PHP_EOL;

$response = $mf->handle('GET', '/status');
echo $response->getStatusCode().' '.$response->getBody().PHP_EOL;

echo $mf->handle('GET', '/priorities')->getBody().PHP_EOL;

// HEAD requests don't have a body
$response = $mf->handle('HEAD', '/status');
echo $response->getStatusCode().' "'.$response->getBody().'"'.PHP_EOL;

// the shutdown dispatch is skipped while testing

?>
--EXPECT--
200 printed only when handled
202 accepted
BA
202 ""
