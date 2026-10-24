--TEST--
CORS middleware
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$mf->testing();
$mf->middleware($mf('cors')->middleware(array(
    'origins' => array('https://app.example.com', 'https://*.example.org'),
    'headers' => array('Content-Type', 'Authorization'),
    'expose' => array('X-Total-Count'),
    'credentials' => true,
    'max_age' => 3600,
)));

$mf->get('/items', function () {
    return array();
});

$show = function ($response) {
    echo $response->getStatusCode().' '.json_encode($response->getHeaders()).PHP_EOL;
};

// preflight
$show($mf->handle('OPTIONS', '/items', array('headers' => array(
    'Origin' => 'https://app.example.com',
    'Access-Control-Request-Method' => 'GET',
))));

// actual request from a wildcard origin
$show($mf->handle('GET', '/items', array('headers' => array('Origin' => 'https://admin.example.org'))));

// not allowed origin and same origin requests don't get CORS headers
$show($mf->handle('GET', '/items', array('headers' => array('Origin' => 'https://evil.test'))));
$show($mf->handle('GET', '/items'));

?>
--EXPECT--
204 {"Access-Control-Allow-Origin":["https:\/\/app.example.com"],"Vary":["Origin"],"Access-Control-Allow-Credentials":["true"],"Access-Control-Allow-Methods":["GET, HEAD, POST, PUT, PATCH, DELETE"],"Access-Control-Allow-Headers":["Content-Type, Authorization"],"Access-Control-Max-Age":["3600"]}
200 {"Content-Type":["application\/json"],"Access-Control-Allow-Origin":["https:\/\/admin.example.org"],"Vary":["Origin"],"Access-Control-Allow-Credentials":["true"],"Access-Control-Expose-Headers":["X-Total-Count"]}
200 {"Content-Type":["application\/json"]}
200 {"Content-Type":["application\/json"]}
