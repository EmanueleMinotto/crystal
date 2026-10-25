--TEST--
Request values read from the superglobals
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'post';
$_SERVER['REQUEST_URI'] = '/users/42?tab=posts';
$_SERVER['SCRIPT_NAME'] = '/';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'it';
$_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
$_GET = ['tab' => 'posts'];
$_POST = ['name' => 'Alice'];
$_COOKIE = ['theme' => 'dark'];

$request = $mf('request');

var_dump($request->method(), $request->path(), $request->ip(), $request->isSecure());
var_dump($request->query('tab'), $request->query('missing', 'default'), $request->query());
var_dump($request->input('name'), $request->isJson());
var_dump($request->header('accept-language'), $request->header('Content-Type'), $request->header('X-Missing', 'none'));
var_dump($request->cookie('theme'), $request->file('avatar'));

// changes to the superglobals are visible
$_SERVER['HTTPS'] = 'on';
var_dump($request->isSecure());

// JSON body
$_SERVER['CONTENT_TYPE'] = 'application/json; charset=utf-8';
$request->setBody('{"name":"Bob","tags":["a"]}');
var_dump($request->isJson(), $request->input('name'), $request->input('missing', 'default'), $request->json()['tags']);
$request->setBody('invalid');
var_dump($request->input());

$_SERVER['REQUEST_METHOD'] = 'POST';
$request->setAttribute('user', null);
var_dump($request->attribute('user', 'default'), $request->attribute('missing', 'default'));

// route parameters are set before each callback
$mf('/users/(?P<id>[0-9]+)', function () use ($request) {
    var_dump($request->param('id'), $request->params());
}, 'POST');

$mf('/(?P<section>[a-z]+)/(?P<id>[0-9]+)', function ($id) use ($request) {
    var_dump($id, $request->params(), $request->param('missing', 'default'));
}, 'POST');

?>
--EXPECT--
string(4) "POST"
string(9) "/users/42"
string(9) "127.0.0.1"
bool(false)
string(5) "posts"
string(7) "default"
array(1) {
  ["tab"]=>
  string(5) "posts"
}
string(5) "Alice"
bool(false)
string(2) "it"
string(33) "application/x-www-form-urlencoded"
string(4) "none"
string(4) "dark"
NULL
bool(true)
bool(true)
string(3) "Bob"
string(7) "default"
array(1) {
  [0]=>
  string(1) "a"
}
array(0) {
}
NULL
string(7) "default"
string(2) "42"
array(1) {
  ["id"]=>
  string(2) "42"
}
string(2) "42"
array(2) {
  ["section"]=>
  string(5) "users"
  ["id"]=>
  string(2) "42"
}
string(7) "default"
