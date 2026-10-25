--TEST--
CORS headers of simple and preflight requests
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/users';
$_SERVER['SCRIPT_NAME'] = '/';

$cors = $mf('cors');

// disabled by default
$_SERVER['HTTP_ORIGIN'] = 'https://app.example.com';
var_dump($cors->isEnabled(), $cors->headers());

$cors->enable([
    'origins' => ['https://app.example.com', 'https://*.example.org'],
    'expose' => ['X-Total-Count'],
    'max_age' => 600,
    'paths' => '/api/.*',
]);
var_dump($cors->isEnabled());
print_r($cors->headers());

// origins matching the wildcard
$_SERVER['HTTP_ORIGIN'] = 'https://a.b.example.org';
print_r($cors->headers());

// origins not allowed, requests without origin and paths not matching
foreach (['https://example.org', 'https://evil.com/.example.org', 'https://app.example.com.evil.com'] as $origin) {
    $_SERVER['HTTP_ORIGIN'] = $origin;
    var_dump($cors->headers());
}

unset($_SERVER['HTTP_ORIGIN']);
var_dump($cors->headers());

$_SERVER['HTTP_ORIGIN'] = 'https://app.example.com';
$_SERVER['REQUEST_URI'] = '/admin';
var_dump($cors->headers());

// preflight requests: the requested method and headers are allowed by default
$_SERVER['REQUEST_URI'] = '/api/users/42?x=1';
$_SERVER['REQUEST_METHOD'] = 'OPTIONS';
$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = 'delete';
$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] = 'Authorization, Content-Type';
var_dump($cors->isPreflight());
print_r($cors->headers());

// any origin, credentials and configured methods and headers
$cors->enable([
    'origins' => ['*'],
    'methods' => ['get', 'POST'],
    'headers' => ['Content-Type'],
    'max_age' => 0,
    'paths' => '.*',
]);
print_r($cors->headers());

$cors->enable(['credentials' => true]);
print_r($cors->headers());

// OPTIONS requests without the preflight headers
unset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']);
var_dump($cors->isPreflight());

try {
    $cors->enable(['origin' => ['*'], 'maxAge' => 1]);
} catch (InvalidArgumentException $exception) {
    echo $exception->getMessage() . "\n";
}

?>
--EXPECT--
bool(false)
array(0) {
}
bool(true)
Array
(
    [Access-Control-Allow-Origin] => https://app.example.com
    [Access-Control-Expose-Headers] => X-Total-Count
    [Vary] => Origin
)
Array
(
    [Access-Control-Allow-Origin] => https://a.b.example.org
    [Access-Control-Expose-Headers] => X-Total-Count
    [Vary] => Origin
)
array(0) {
}
array(0) {
}
array(0) {
}
array(0) {
}
array(0) {
}
bool(true)
Array
(
    [Access-Control-Allow-Origin] => https://app.example.com
    [Access-Control-Allow-Methods] => DELETE
    [Access-Control-Allow-Headers] => Authorization, Content-Type
    [Access-Control-Max-Age] => 600
    [Vary] => Origin, Access-Control-Request-Method, Access-Control-Request-Headers
)
Array
(
    [Access-Control-Allow-Origin] => *
    [Access-Control-Allow-Methods] => GET, POST
    [Access-Control-Allow-Headers] => Content-Type
)
Array
(
    [Access-Control-Allow-Origin] => https://app.example.com
    [Access-Control-Allow-Credentials] => true
    [Access-Control-Allow-Methods] => GET, POST
    [Access-Control-Allow-Headers] => Content-Type
    [Vary] => Origin
)
bool(false)
Unknown CORS options: origin, maxAge.
