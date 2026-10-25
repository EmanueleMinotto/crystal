--TEST--
Route values that can't be passed to typed parameters are a 404
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/users/abc';
$_SERVER['SCRIPT_NAME'] = '/';
$_SERVER['HTTP_ACCEPT'] = 'application/json';

$mf('errors')->enable();

$mf('/users/{id}', function (int $id) {
    echo "not invoked\n";
});

register_shutdown_function(function () {
    echo "\n";
    var_dump(http_response_code());
});

?>
--EXPECT--
{"error":{"status":404,"title":"Not Found"}}
int(404)
