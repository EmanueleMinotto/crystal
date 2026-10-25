--TEST--
Built-in validation rules
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$validator = $mf('validator');

$cases = [
    ['required', [], ['missing', null, '', []], [0, '0', false, ' ']],
    ['string', [], [1, ['a']], ['a']],
    ['integer', [], ['1.5', 'a', 1.5, '1e3'], [1, '-12', '0']],
    ['numeric', [], ['a', '1,5'], [1, '1.5', '-3', '1e3']],
    ['boolean', [], ['yes', 2, 'on'], [true, false, 0, 1, '0', '1', 'true', 'false']],
    ['array', [], ['a', 1], [['a']]],
    ['email', [], ['a@', 'a.example.com', ['a@example.com']], ['a@example.com']],
    ['url', [], ['example.com', 'http//x'], ['https://example.com/path?q=1']],
    ['ip', [], ['256.1.1.1', 'localhost'], ['127.0.0.1', '::1']],
    ['date', [], ['not a date', 1], ['2026-10-25', 'tomorrow']],
    ['date', 'Y-m-d', ['25/10/2026', '2026-02-30', '2026-10-25 10:00'], ['2026-10-25']],
    ['min', 3, ['ab', 'àè', ['a']], ['abc', 'àèì', ['a', 'b', 'c']]],
    ['max', 3, ['abcd', ['a', 'b', 'c', 'd']], ['abc', 'àèì']],
    ['between', [2, 3], ['a', 'abcd'], ['ab', 'abc']],
    ['in', ['admin', 'editor', 1], ['user', ['admin'], 2], ['admin', '1', 1]],
    ['regex', '/^[a-z]+$/', ['ABC', 'a1', ['a']], ['abc']],
];

foreach ($cases as $case) {
    list($rule, $param, $invalid, $valid) = $case;
    $rules = ['field' => [] === $param ? [$rule] : [$rule => $param]];

    $results = [];

    foreach ($invalid as $value) {
        $data = 'missing' === $value ? [] : ['field' => $value];
        $results[] = empty($validator->check($data, $rules)) ? 'FAIL' : 'ok';
    }

    foreach ($valid as $value) {
        $results[] = empty($validator->check(['field' => $value], $rules)) ? 'ok' : 'FAIL';
    }

    echo str_pad($rule, 9) . implode(' ', $results) . "\n";
}

// numeric fields are compared by value
var_dump($validator->check(['age' => '17'], ['age' => ['integer', 'min' => 18]]));
var_dump($validator->check(['age' => '120'], ['age' => ['numeric', 'between' => [18, 120]]]));
var_dump($validator->check(['age' => 17], ['age' => ['min' => 18]]));

// same and confirmed
var_dump($validator->check(['a' => 'x', 'b' => 'x'], ['a' => ['same' => 'b']]));
var_dump(count($validator->check(['a' => 'x', 'b' => 'y'], ['a' => ['same' => 'b']])));
var_dump(count($validator->check(['a' => 'x'], ['a' => ['same' => 'b']])));
var_dump($validator->check(['password' => 's', 'password_confirmation' => 's'], ['password' => ['confirmed']]));
var_dump(count($validator->check(['password' => 's'], ['password' => ['confirmed']])));

?>
--EXPECT--
required ok ok ok ok ok ok ok ok
string   ok ok ok
integer  ok ok ok ok ok ok ok
numeric  ok ok ok ok ok ok
boolean  ok ok ok ok ok ok ok ok ok ok ok
array    ok ok ok
email    ok ok ok ok
url      ok ok ok
ip       ok ok ok ok
date     ok ok ok ok
date     ok ok ok ok
min      ok ok ok ok ok ok
max      ok ok ok ok
between  ok ok ok ok
in       ok ok ok ok ok ok
regex    ok ok ok ok
array(1) {
  ["age"]=>
  array(1) {
    [0]=>
    string(34) "The age field must be at least 18."
  }
}
array(0) {
}
array(1) {
  ["age"]=>
  array(1) {
    [0]=>
    string(34) "The age field must be at least 18."
  }
}
array(0) {
}
int(1)
int(1)
array(0) {
}
int(1)
