--TEST--
Paths always answered in JSON
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/app/api/users';
$_SERVER['SCRIPT_NAME'] = '/app/index.php';

$errors = $mf('errors');

try {
    $errors->enable(false, ['json' => '/api/.*', 'format' => 'json']);
} catch (InvalidArgumentException $exception) {
    echo $exception->getMessage()."\n";
}

var_dump($errors->isEnabled());

// HTML when the client doesn't ask for JSON
$response = $errors->response(404);
echo $response->header('Content-Type')."\n";

$errors->enable(false, ['json' => '/api/.*']);

// paths relative to the base path, like the routes
$response = $errors->response(404);
echo $response->header('Content-Type').' '.$response->body()."\n";

$_SERVER['REQUEST_URI'] = '/app/users';
echo $errors->response(404)->header('Content-Type')."\n";

?>
--EXPECT--
Unknown errors options: format.
bool(false)
text/html; charset=utf-8
application/json {"error":{"status":404,"title":"Not Found"}}
text/html; charset=utf-8
