--TEST--
Malformed JSON bodies are a 400 response
--FILE--
<?php

ob_start();

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/users';
$_SERVER['SCRIPT_NAME'] = '/';
$_SERVER['CONTENT_TYPE'] = 'application/json';
$_SERVER['HTTP_ACCEPT'] = 'application/json';

$request = $mf('request');

// empty bodies aren't malformed
$request->setBody('');
var_dump($request->isMalformedJson(), $request->validate(['name' => []]));

$request->setBody('{"name": "Alice",}');
var_dump($request->isMalformedJson());

// without the errors handling, input() is empty
var_dump($request->input());

// validate() always stops the request
try {
    $request->validate(['name' => ['required']]);
} catch (RuntimeException $exception) {
    var_dump($exception->getStatusCode(), $exception->getMessage());
}

$mf('errors')->enable();

$mf('/users', function () use ($mf) {
    $mf('request')->input('name');

    echo "not invoked\n";
}, 'POST');

register_shutdown_function(function () {
    echo "\n";
    var_dump(http_response_code());
});

?>
--EXPECT--
bool(false)
array(0) {
}
bool(true)
array(0) {
}
int(400)
string(34) "Malformed JSON body: Syntax error."
{"error":{"status":400,"title":"Bad Request","message":"Malformed JSON body: Syntax error."}}
int(400)
