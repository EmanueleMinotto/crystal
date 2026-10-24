--TEST--
Request service
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$mf->testing();

$mf->route(array('POST', 'PUT'), '/orders/{id}', function () use ($mf) {
    $request = $mf('request');

    return array(
        'method' => $request->method(),
        'path' => $request->path(),
        'param' => $request->param('id'),
        'query' => $request->query('expand'),
        'missing' => $request->query('missing', 'default'),
        'json' => $request->isJson(),
        'input' => $request->input('customer.name'),
        'all' => $request->all(),
        'header' => $request->header('x-trace-id'),
        'token' => $request->bearerToken(),
        'wantsJson' => $request->wantsJson(),
    );
});

$response = $mf->handle('POST', '/orders/7?expand=items', array(
    'headers' => array('X-Trace-Id' => 'abc', 'Authorization' => 'Bearer s3cr3t'),
    'json' => array('customer' => array('name' => 'Alice')),
));
echo json_encode($response->json(), JSON_PRETTY_PRINT).PHP_EOL;

// form data sent with PUT is parsed too
$response = $mf->handle('PUT', '/orders/8', array('form' => array('status' => 'paid')));
echo json_encode($response->json()['all']).PHP_EOL;

// requests built from globals
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/search?q=php';
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_GET = array('q' => 'php');

$request = $mf('request');
$request->initialize();
var_dump($request->path(), $request->query('q'), $request->header('Accept'), $request->ip(), $request->wantsJson());

?>
--EXPECT--
{
    "method": "POST",
    "path": "\/orders\/7",
    "param": "7",
    "query": "items",
    "missing": "default",
    "json": true,
    "input": "Alice",
    "all": {
        "customer": {
            "name": "Alice"
        },
        "expand": "items"
    },
    "header": "abc",
    "token": "s3cr3t",
    "wantsJson": true
}
{"status":"paid"}
string(7) "/search"
string(3) "php"
string(16) "application/json"
string(9) "127.0.0.1"
bool(true)
