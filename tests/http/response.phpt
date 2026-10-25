--TEST--
Response objects
--FILE--
<?php

ob_start();

$mf = require_once(__DIR__.'/../../crystal.php');

$response = $mf('response');
var_dump($response !== $mf('response'));
var_dump($response->status(), $response->body(), $response->headers(), $response->cookies());

$response
    ->setStatus(201)
    ->setHeader('X-Custom', 'one')
    ->setHeader('x-custom', 'two')
    ->setHeader('Cache-Control', 'no-cache')
    ->removeHeader('CACHE-CONTROL')
    ->setBody('Hello')
    ->write(' World');

var_dump($response->status(), $response->header('X-CUSTOM'), $response->header('Missing'), $response->headers(), $response->body());

$response->cookie('session', 'abc', ['expires' => 10]);
$response->forgetCookie('old');
$cookies = $response->cookies();
var_dump($cookies['session'][0], $cookies['session'][1]['expires'], $cookies['session'][1]['httponly'], $cookies['session'][1]['samesite']);
var_dump($cookies['old'][0], $cookies['old'][1]['expires'] < time());

$json = $mf('response')->json(['ok' => true], 422);
var_dump($json->status(), $json->header('content-type'), $json->body());
var_dump($mf('response')->setStatus(201)->json([])->status());

try {
    $mf('response')->json(NAN);
} catch (InvalidArgumentException $exception) {
    echo get_class($exception) . "\n";
}

$redirect = $mf('response')->redirect('/login');
var_dump($redirect->status(), $redirect->header('Location'));
var_dump($mf('response')->redirect('/moved', 301)->status());

$mf('response')->setStatus(404)->setBody("sent\n")->send();
var_dump(http_response_code());

?>
--EXPECT--
bool(true)
int(200)
string(0) ""
array(0) {
}
array(0) {
}
int(201)
string(3) "two"
NULL
array(1) {
  ["x-custom"]=>
  string(3) "two"
}
string(11) "Hello World"
string(3) "abc"
int(10)
bool(true)
string(3) "Lax"
string(0) ""
bool(true)
int(422)
string(16) "application/json"
string(11) "{"ok":true}"
int(201)
InvalidArgumentException
int(302)
string(6) "/login"
int(301)
sent
int(404)
