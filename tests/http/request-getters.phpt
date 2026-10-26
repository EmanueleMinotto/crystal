--TEST--
Typed values of the request
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/';
$_GET = ['page' => '2', 'q' => 'php', 'email' => 'query@example.com'];
$_POST = ['email' => ['alice@example.com'], 'age' => '30', 'price' => '9.90', 'newsletter' => 'on', 'terms' => 'maybe', 'empty' => ''];

$request = $mf('request');

var_dump($request->string('q'), $request->string('email'), $request->string('email', 'invalid'), $request->string('missing'));
var_dump($request->integer('page'), $request->integer('age'), $request->integer('price'), $request->integer('missing', 1));
var_dump($request->boolean('newsletter'), $request->boolean('terms', true), $request->boolean('empty', true), $request->boolean('missing'));
var_dump($request->has('empty'), $request->has('missing'), $request->has('page'));
var_dump($request->only(['q', 'age', 'missing']));

?>
--EXPECT--
string(3) "php"
string(0) ""
string(7) "invalid"
string(0) ""
int(2)
int(30)
int(0)
int(1)
bool(true)
bool(true)
bool(false)
bool(false)
bool(true)
bool(false)
bool(true)
array(2) {
  ["q"]=>
  string(3) "php"
  ["age"]=>
  string(2) "30"
}
