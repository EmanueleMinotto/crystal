--TEST--
Problem Details (RFC 9457) error responses
--FILE--
<?php

ob_start();

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/users';
$_SERVER['SCRIPT_NAME'] = '/';
$_POST = ['age' => 'old'];

$errors = $mf('errors');
$errors->enable(false, ['json' => '/api/.*', 'problem' => true]);

$response = $errors->response(404);
echo $response->header('Content-Type').' '.$response->body()."\n";

// details in debug mode
$errors->enable(true);
$problem = json_decode($errors->handle(new LogicException('Oops'))->body(), true);
var_dump($problem['detail'], $problem['exception'], isset($problem['file'], $problem['trace']));

// validation errors, with the status code
$errors->enable(false);

$mf('/api/users', function () use ($mf) {
    $mf('request')->validate(['age' => ['integer']]);
}, 'POST');

register_shutdown_function(function () {
    echo "\n";
    var_dump(http_response_code());
});

?>
--EXPECT--
application/problem+json {"type":"about:blank","title":"Not Found","status":404}
string(4) "Oops"
string(14) "LogicException"
bool(true)
{"type":"about:blank","title":"Unprocessable Content","status":422,"detail":"The given data is invalid.","errors":{"age":["The age field must be an integer."]}}
int(422)
