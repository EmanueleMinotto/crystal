--TEST--
Error details in debug mode, as HTML and JSON
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/';

$errors = $mf('errors');
$errors->enable(true);
$errors->setReporter(function () {});

$exception = new LogicException('<b>broken</b>');

$html = $errors->handle($exception);
var_dump($html->status(), $html->header('Content-Type'));
var_dump(false !== strpos($html->body(), '<p>&lt;b&gt;broken&lt;/b&gt;</p>'));
var_dump(false !== strpos($html->body(), '<code>LogicException</code> in <code>'.$exception->getFile().':'));
var_dump(false !== strpos($html->body(), '<pre>#0'));

$_SERVER['HTTP_ACCEPT'] = 'application/json';
$json = $errors->handle($exception);
$error = json_decode($json->body(), true)['error'];
var_dump($json->header('Content-Type'), $error['status'], $error['title'], $error['message'], $error['exception']);
var_dump($error['file'] === $exception->getFile().':'.$exception->getLine(), is_array($error['trace']));

// without debug only the status is shown
$errors->enable(false);
var_dump(json_decode($errors->handle($exception)->body(), true));

// JSON requests receive JSON errors too
unset($_SERVER['HTTP_ACCEPT']);
$_SERVER['CONTENT_TYPE'] = 'application/json';
var_dump($errors->response(422)->body());

?>
--EXPECT--
int(500)
string(24) "text/html; charset=utf-8"
bool(true)
bool(true)
bool(true)
string(16) "application/json"
int(500)
string(21) "Internal Server Error"
string(13) "<b>broken</b>"
string(14) "LogicException"
bool(true)
bool(true)
array(1) {
  ["error"]=>
  array(2) {
    ["status"]=>
    int(500)
    ["title"]=>
    string(21) "Internal Server Error"
  }
}
string(56) "{"error":{"status":422,"title":"Unprocessable Content"}}"
