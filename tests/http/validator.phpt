--TEST--
Validator service
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$validator = $mf('validator');

$rules = array(
    'email' => 'required|email',
    'name' => 'required|string|between:2,10',
    'age' => array('nullable', 'integer', 'min:18'),
    'role' => 'in:admin,user',
    'tags' => 'array|max:2',
    'address.city' => 'required|string',
    'code' => array('required', function ($value) {
        return 0 === strpos($value, 'X') ?: 'The code must start with X.';
    }),
    'password' => 'required|confirmed',
    'zip' => 'regex:/^\d{5}$/',
);

echo json_encode($validator->errors(array(
    'email' => 'not-an-email',
    'name' => 'A',
    'age' => '12',
    'role' => 'root',
    'tags' => array(1, 2, 3),
    'address' => array(),
    'code' => 'Y1',
    'password' => 'a',
    'password_confirmation' => 'b',
    'zip' => '123',
), $rules, array('email.email' => 'Custom email message.')), JSON_PRETTY_PRINT).PHP_EOL;

echo json_encode($validator->validate(array(
    'email' => 'bob@example.com',
    'name' => 'Bob',
    'age' => null,
    'address' => array('city' => 'Rome', 'street' => 'ignored'),
    'code' => 'X1',
    'password' => 'secret',
    'password_confirmation' => 'secret',
    'extra' => 'ignored',
), $rules)).PHP_EOL;

try {
    $validator->validate(array(), array('email' => 'required'));
} catch (RuntimeException $e) {
    echo $e->getStatusCode().' '.json_encode($e->getExtensions()).PHP_EOL;
}

try {
    $validator->validate(array('a' => 1), array('a' => 'unknown'));
} catch (InvalidArgumentException $e) {
    echo $e->getMessage().PHP_EOL;
}

?>
--EXPECT--
{
    "email": [
        "Custom email message."
    ],
    "name": [
        "The name field must be between 2 and 10."
    ],
    "age": [
        "The age field must be at least 18."
    ],
    "role": [
        "The selected role is invalid."
    ],
    "tags": [
        "The tags field must not be greater than 2."
    ],
    "address.city": [
        "The address.city field is required."
    ],
    "code": [
        "The code must start with X."
    ],
    "password": [
        "The password field confirmation does not match."
    ],
    "zip": [
        "The zip field format is invalid."
    ]
}
{"email":"bob@example.com","name":"Bob","age":null,"address":{"city":"Rome"},"code":"X1","password":"secret"}
422 {"errors":{"email":["The email field is required."]}}
Unknown validation rule "unknown"
