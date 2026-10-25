--TEST--
Validation of nested fields and conversion of the validated values
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$validator = $mf('validator');

$data = [
    'name' => 'Order',
    'address' => ['city' => 'Rome', 'zip' => 'abc'],
    'items' => [
        ['sku' => 'A1', 'quantity' => '2'],
        ['sku' => 'B2', 'quantity' => 'many'],
        ['quantity' => '1'],
    ],
    'tags.main' => 'literal',
];

$rules = [
    'address.city' => ['required', 'string'],
    'address.zip' => ['regex' => '/^[0-9]{5}$/'],
    'address.country' => ['required'],
    'items' => ['required', 'array'],
    'items.*.sku' => ['required', 'string'],
    'items.*.quantity' => ['integer', 'max' => 5],
    'tags.main' => ['string'],
    'notes.*.text' => ['required'],
];

print_r($validator->check($data, $rules, [
    'items.*.sku.required' => 'Each item needs a SKU.',
    'items.2.quantity.max' => 'Unused.',
]));

// only the validated nested fields are returned
unset($rules['address.zip'], $rules['address.country']);
$data['items'][1]['quantity'] = '3';
unset($data['items'][2]);

var_dump($validator->validate($data, [
    'address.city' => ['string'],
    'items.*.quantity' => ['integer'],
    'tags.main' => ['string'],
]));

// conversion of integer, numeric and boolean values
$validator->castTypes();

var_dump($validator->validate(
    ['age' => '30', 'price' => '9.90', 'active' => 'false', 'name' => '42', 'empty' => '', 'items' => [['quantity' => '2']]],
    ['age' => ['integer'], 'price' => ['numeric'], 'active' => ['boolean'], 'name' => ['string'], 'empty' => ['integer'], 'items.*.quantity' => ['integer']]
));

?>
--EXPECT--
Array
(
    [address.zip] => Array
        (
            [0] => The address.zip field format is invalid.
        )

    [address.country] => Array
        (
            [0] => The address.country field is required.
        )

    [items.2.sku] => Array
        (
            [0] => Each item needs a SKU.
        )

    [items.1.quantity] => Array
        (
            [0] => The items.1.quantity field must be an integer.
        )

)
array(3) {
  ["tags.main"]=>
  string(7) "literal"
  ["address"]=>
  array(1) {
    ["city"]=>
    string(4) "Rome"
  }
  ["items"]=>
  array(2) {
    [0]=>
    array(1) {
      ["quantity"]=>
      string(1) "2"
    }
    [1]=>
    array(1) {
      ["quantity"]=>
      string(1) "3"
    }
  }
}
array(6) {
  ["age"]=>
  int(30)
  ["price"]=>
  float(9.9)
  ["active"]=>
  bool(false)
  ["name"]=>
  string(2) "42"
  ["empty"]=>
  string(0) ""
  ["items"]=>
  array(1) {
    [0]=>
    array(1) {
      ["quantity"]=>
      int(2)
    }
  }
}
