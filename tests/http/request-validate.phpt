--TEST--
Validation of the request values
--FILE--
<?php

ob_start();

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/users?page=2&name=query';
$_SERVER['SCRIPT_NAME'] = '/';
$_SERVER['CONTENT_TYPE'] = 'application/json';
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$_GET = ['page' => '2', 'name' => 'query'];

$request = $mf('request');
$rules = [
    'name' => ['required', 'string'],
    'age' => ['integer', 'min' => 18],
    'page' => ['integer'],
];

// JSON body values take precedence over the query string, only the fields with rules are returned
$request->setBody('{"name": "Alice", "age": 30, "admin": true}');
var_dump($request->validate($rules));

// form values
$_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
$_POST = ['name' => 'Bob'];
var_dump($request->validate($rules));

// invalid values, with custom messages
$_POST = ['age' => '12'];
$_GET = [];

try {
    $request->validate($rules, ['name.required' => 'Who are you?']);
} catch (RuntimeException $exception) {
    var_dump($exception->getStatusCode());
    print_r($exception->getErrors());
}

// 422 response sent by the errors service
$mf('errors')->enable();

$mf('/users', function () use ($mf) {
    $data = $mf('request')->validate(['name' => ['required']]);

    echo "not invoked\n";
}, 'POST');

register_shutdown_function(function () {
    echo "\n";
    var_dump(http_response_code());
});

?>
--EXPECT--
array(3) {
  ["page"]=>
  string(1) "2"
  ["name"]=>
  string(5) "Alice"
  ["age"]=>
  int(30)
}
array(2) {
  ["page"]=>
  string(1) "2"
  ["name"]=>
  string(3) "Bob"
}
int(422)
Array
(
    [name] => Array
        (
            [0] => Who are you?
        )

    [age] => Array
        (
            [0] => The age field must be at least 18.
        )

)
{"error":{"status":422,"title":"Unprocessable Content","message":"The given data is invalid.","errors":{"name":["The name field is required."]}}}
int(422)
