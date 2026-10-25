--TEST--
Allow header of the 405 responses
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$errors = $mf('errors');

$response = $errors->notFound(['GET|post', 'PUT', 'GET', 'PATCH|(DELETE)']);
var_dump($response->status(), $response->header('Allow'));

$response = $errors->notFound(['(GET)']);
var_dump($response->status(), $response->header('Allow'));

var_dump($errors->notFound()->status());

?>
--EXPECT--
int(405)
string(14) "GET, POST, PUT"
int(405)
NULL
int(404)
