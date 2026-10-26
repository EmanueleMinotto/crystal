--TEST--
Wiki: the application of the Getting Started page
--SKIPIF--
<?php if (!extension_loaded('pdo_sqlite')) die('skip pdo_sqlite is required'); ?>
--FILE--
<?php

require __DIR__.'/helpers.php';

$app = docs_app('Getting-Started');
$json = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];

$result = docs_request($app, 'GET', '/hello/Alice');
echo $result['status'].' '.$result['body']."\n";

$result = docs_request($app, 'POST', '/api/notes', ['headers' => $json, 'body' => '{"text":"Buy milk"}']);
echo $result['status'].' '.$result['body']."\n";

$result = docs_request($app, 'POST', '/api/notes', ['headers' => $json, 'body' => '{}']);
echo $result['status'].' '.$result['body']."\n";

$result = docs_request($app, 'GET', '/api/notes');
$notes = json_decode($result['body'], true);
echo $result['status'].' '.count($notes).' '.$notes[0]['text']."\n";

$result = docs_request($app, 'GET', '/');
echo $result['status'].' '.preg_replace('/\s+/', ' ', strip_tags($result['body']))."\n";

$result = docs_request($app, 'GET', '/missing');
echo $result['status']."\n";

?>
--EXPECT--
200 Hello Alice!
201 {"text":"Buy milk","id":1}
422 {"error":{"status":422,"title":"Unprocessable Content","message":"The given data is invalid.","errors":{"text":["The text field is required."]}}}
200 1 Buy milk
200  Notes Buy milk 
404
