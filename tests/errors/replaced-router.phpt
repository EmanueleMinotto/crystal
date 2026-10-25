--TEST--
Allow header of the 405 responses when the router has been replaced
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$mf('router', new stdClass());

$response = $mf('errors')->notFound(['GET|post', 'PUT', 'GET', '(PATCH)']);
var_dump($response->status(), $response->header('Allow'));

?>
--EXPECT--
int(405)
string(14) "GET, POST, PUT"
