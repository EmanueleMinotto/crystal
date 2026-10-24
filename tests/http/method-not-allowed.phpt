--TEST--
Automatic 404, 405 and OPTIONS responses
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$mf->testing();

$mf->get('/users', function () {
    return array();
});
$mf->post('/users', function () {
    return array();
});
$mf('/legacy', function () {
    echo 'legacy';
}, 'GET|PUT');

foreach (array(
    array('DELETE', '/users'),
    array('OPTIONS', '/users'),
    array('POST', '/legacy'),
    array('GET', '/missing'),
) as $request) {
    $response = $mf->handle($request[0], $request[1]);
    echo rtrim($request[0].' '.$request[1].' => '.$response->getStatusCode().' Allow: '.$response->getHeaderLine('Allow')).PHP_EOL;
}

echo $mf->handle('DELETE', '/users')->getBody().PHP_EOL;

?>
--EXPECT--
DELETE /users => 405 Allow: GET, HEAD, POST, OPTIONS
OPTIONS /users => 204 Allow: GET, HEAD, POST, OPTIONS
POST /legacy => 405 Allow: GET, PUT, OPTIONS
GET /missing => 404 Allow:
{"type":"about:blank","title":"Method Not Allowed","status":405}
