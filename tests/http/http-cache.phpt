--TEST--
Conditional requests with ETag and Last-Modified
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$mf->testing();
$mf->middleware($mf('response')->conditional());

$mf->get('/articles', function () use ($mf) {
    return $mf('response')->json(array('a', 'b'))->withCache(60);
});
$mf->get('/archive', function () use ($mf) {
    return $mf('response')->text('old')->withLastModified(1000000000);
});
$mf->post('/articles', function () {
    return array('created' => true);
});

$first = $mf->handle('GET', '/articles');
$etag = $first->getHeaderLine('ETag');
echo $first->getStatusCode().' '.$etag.PHP_EOL;

$second = $mf->handle('GET', '/articles', array('headers' => array('If-None-Match' => 'W/'.$etag)));
echo $second->getStatusCode().' '.json_encode($second->getHeaders()).' "'.$second->getBody().'"'.PHP_EOL;

echo $mf->handle('GET', '/articles', array('headers' => array('If-None-Match' => '"other"')))->getStatusCode().PHP_EOL;
echo $mf->handle('GET', '/archive', array('headers' => array('If-Modified-Since' => 'Sun, 09 Sep 2001 01:46:40 GMT')))->getStatusCode().PHP_EOL;
echo $mf->handle('GET', '/archive', array('headers' => array('If-Modified-Since' => 'Sat, 08 Sep 2001 00:00:00 GMT')))->getStatusCode().PHP_EOL;
echo json_encode($mf->handle('POST', '/articles')->hasHeader('ETag')).PHP_EOL;

?>
--EXPECT--
200 "e53f04d1bcc1428d9e8db9a93578c5eb"
304 {"Cache-Control":["public, max-age=60"],"ETag":["\"e53f04d1bcc1428d9e8db9a93578c5eb\""]} ""
200
304
200
false
