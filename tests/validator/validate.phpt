--TEST--
Validation results, messages and custom rules
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$validator = $mf('validator');

$rules = [
    'email' => ['required', 'email', 'max' => 20],
    'full_name' => ['required', 'string'],
    'age' => ['integer', 'between' => [18, 120]],
    'role' => ['in' => ['admin', 'editor']],
    'nickname' => ['string'],
];

// valid data: only the fields with rules are returned, empty optional fields are valid
var_dump($validator->validate([
    'email' => 'alice@example.com',
    'full_name' => 'Alice',
    'age' => '',
    'admin' => true,
], $rules));

// all the errors of each field, except after required
print_r($validator->check([
    'email' => 'not-an-email-and-too-long',
    'age' => '12',
    'role' => 'user',
], $rules));

// custom messages, by field and rule or by rule
print_r($validator->check(['age' => 'a'], ['age' => ['required', 'integer'], 'email' => ['required']], [
    'age.integer' => 'Age is a number.',
    'required' => ':field is mandatory.',
]));

// inline custom rules: true, false or a message
$even = function ($value) {
    return 0 === $value % 2;
};
$different = function ($value, $field, $data) {
    return $value !== $data['username'] ? true : 'The :field field must differ from the username.';
};
print_r($validator->check(['number' => 3, 'password' => 'bob', 'username' => 'bob'], [
    'number' => ['integer', $even],
    'password' => [$different],
]));

// reusable rules
$validator->rule('slug', function ($value) {
    return 1 === preg_match('/^[a-z0-9-]+$/', $value);
}, 'The :field field must be a slug.');
$validator->rule('multiple', function ($value, $param) {
    return 0 === $value % $param ? true : 'The :field field must be a multiple of :param.';
});
print_r($validator->check(['slug' => 'Not A Slug', 'count' => 7], [
    'slug' => ['slug'],
    'count' => ['multiple' => 5],
]));

// validate throws an exception handled by the errors service
try {
    $validator->validate(['email' => 'x'], $rules);
} catch (RuntimeException $exception) {
    var_dump($exception->getMessage(), $exception->getStatusCode(), $exception->getErrors());

    $_SERVER['HTTP_ACCEPT'] = 'application/json';
    echo $mf('errors')->handle($exception)->body() . "\n";

    unset($_SERVER['HTTP_ACCEPT']);
    $body = $mf('errors')->handle($exception)->body();
    echo substr($body, strpos($body, '<h1>'), -strlen('</body></html>')) . "\n";
}

// developer errors
foreach ([['email' => 'required'], ['email' => ['unknown']], ['email' => [42]]] as $invalid) {
    try {
        $validator->check(['email' => 'x'], $invalid);
    } catch (InvalidArgumentException $exception) {
        echo $exception->getMessage() . "\n";
    }
}

?>
--EXPECT--
array(3) {
  ["email"]=>
  string(17) "alice@example.com"
  ["full_name"]=>
  string(5) "Alice"
  ["age"]=>
  string(0) ""
}
Array
(
    [email] => Array
        (
            [0] => The email field must be a valid email address.
            [1] => The email field must not be greater than 20.
        )

    [full_name] => Array
        (
            [0] => The full name field is required.
        )

    [age] => Array
        (
            [0] => The age field must be between 18 and 120.
        )

    [role] => Array
        (
            [0] => The selected role is invalid.
        )

)
Array
(
    [age] => Array
        (
            [0] => Age is a number.
        )

    [email] => Array
        (
            [0] => email is mandatory.
        )

)
Array
(
    [number] => Array
        (
            [0] => The number field is invalid.
        )

    [password] => Array
        (
            [0] => The password field must differ from the username.
        )

)
Array
(
    [slug] => Array
        (
            [0] => The slug field must be a slug.
        )

    [count] => Array
        (
            [0] => The count field must be a multiple of 5.
        )

)
string(26) "The given data is invalid."
int(422)
array(2) {
  ["email"]=>
  array(1) {
    [0]=>
    string(46) "The email field must be a valid email address."
  }
  ["full_name"]=>
  array(1) {
    [0]=>
    string(32) "The full name field is required."
  }
}
{"error":{"status":422,"title":"Unprocessable Content","message":"The given data is invalid.","errors":{"email":["The email field must be a valid email address."],"full_name":["The full name field is required."]}}}
<h1>422 Unprocessable Content</h1><p>The given data is invalid.</p><ul><li>The email field must be a valid email address.</li><li>The full name field is required.</li></ul>
Rules of the "email" field must be an array.
Rule "unknown" not defined.
Invalid rule for the "email" field.
