--TEST--
Only the first matching route invoked
--FILE--
<?php

ob_start();

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/users/new';
$_SERVER['SCRIPT_NAME'] = '/';

$router = $mf('router');
var_dump($router->isFirstMatch());
$router->firstMatch();
var_dump($router->isFirstMatch());

$mf->get('/users/{id}', function ($id) {
    echo "user $id\n";
}, 1);

// with the priority 0, it runs first
$mf->get('/users/new', function () {
    echo "form\n";
});

$mf->get('/users/(.*)', function () {
    echo "skipped\n";
});

?>
--EXPECT--
bool(false)
bool(true)
form
