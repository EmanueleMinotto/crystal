--TEST--
Entity tags and 304 responses
--FILE--
<?php

ob_start();

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/users';
$_SERVER['SCRIPT_NAME'] = '/';

$response = $mf('response')->setBody('Hello');
var_dump($response->etag());

// the hash of the body, when it's sent
$response->setEtag();
var_dump($response->etag() === '"'.sha1('Hello').'"');
var_dump($mf('response')->setEtag('v"1', true)->etag());

// weak comparison, lists and wildcard
$response = $mf('response')->setEtag('v1');
var_dump($response->isNotModified());

foreach (['"v1"', 'W/"v1"', '"v0", "v1"', '*', '"v2"'] as $header) {
    $_SERVER['HTTP_IF_NONE_MATCH'] = $header;
    var_dump($response->isNotModified());
}

$_SERVER['HTTP_IF_NONE_MATCH'] = '"v1"';

// only GET and HEAD requests, only 2xx responses
var_dump($response->setStatus(404)->isNotModified());
$response->setStatus(200);
$_SERVER['REQUEST_METHOD'] = 'POST';
var_dump($response->isNotModified());
$_SERVER['REQUEST_METHOD'] = 'GET';

$mf('/users', function () use ($mf) {
    return $mf('response')->json(['users' => []])->setEtag('v1');
});

register_shutdown_function(function () {
    echo "\n";
    var_dump(http_response_code());
});

?>
--EXPECT--
NULL
bool(true)
string(6) "W/"v1""
bool(false)
bool(true)
bool(true)
bool(true)
bool(true)
bool(false)
bool(false)
bool(false)

int(304)
