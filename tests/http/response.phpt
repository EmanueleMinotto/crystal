--TEST--
Response factory and immutable responses
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$factory = $mf('response');

$response = $factory->json(array('name' => 'Crystal', 'url' => 'https://example.com/è'), 201, array('X-Id' => '1'));
echo $response->getStatusCode().' '.$response->getHeaderLine('content-type').' '.$response->getBody().PHP_EOL;

$changed = $response->withStatus(202)->withHeader('X-Id', '2')->withAddedHeader('X-Id', '3')->withoutHeader('Content-Type');
echo $response->getStatusCode().' '.$response->getHeaderLine('X-Id').PHP_EOL;
echo $changed->getStatusCode().' '.$changed->getHeaderLine('X-Id').' '.json_encode($changed->hasHeader('Content-Type')).PHP_EOL;

echo json_encode($factory->text('hi')->getHeaders()).PHP_EOL;
echo json_encode($factory->redirect('/login', 303)->getHeaders()).PHP_EOL;
echo $factory->noContent()->getStatusCode().PHP_EOL;
echo $factory->created('/users/1', array('id' => 1))->getHeaderLine('Location').PHP_EOL;
echo $factory->problem(404, 'User 1 not found', array('instance' => '/users/1'))->getBody().PHP_EOL;

$cached = $factory->text('x')->withCache(60)->withEtag('abc')->withLastModified(0);
echo json_encode($cached->getHeaders()).PHP_EOL;
echo $factory->text('x')->withCache(0)->getHeaderLine('Cache-Control').PHP_EOL;

var_dump($factory->isResponse($response), $factory->isResponse(new stdClass()));

try {
    $factory->json("\xB1\x31");
} catch (InvalidArgumentException $e) {
    echo $e->getMessage().PHP_EOL;
}

?>
--EXPECT--
201 application/json {"name":"Crystal","url":"https://example.com/è"}
201 1
202 2, 3 false
{"Content-Type":["text\/plain; charset=UTF-8"]}
{"Location":["\/login"]}
204
/users/1
{"type":"about:blank","title":"Not Found","status":404,"detail":"User 1 not found","instance":"/users/1"}
{"Content-Type":["text\/plain; charset=UTF-8"],"Cache-Control":["public, max-age=60"],"ETag":["\"abc\""],"Last-Modified":["Thu, 01 Jan 1970 00:00:00 GMT"]}
no-store
bool(true)
bool(false)
Unable to encode JSON: Malformed UTF-8 characters, possibly incorrectly encoded
