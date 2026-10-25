--TEST--
Routes defined with the methods of the framework instance and of the groups
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/users';
$_SERVER['SCRIPT_NAME'] = '/';

$mf->get('/users', function () {
    echo "GET\n";
});

$mf->post('/users', function () {
    echo "POST\n";
}, 0, 'users.store');

$mf->put('/users', function () {
    echo "PUT\n";
});

$mf->patch('/users', function () {
    echo "PATCH\n";
});

$mf->delete('/users', function () {
    echo "DELETE\n";
});

$mf('router')->group('', function ($route) {
    $route->get('/users', function () {
        echo "group GET\n";
    });

    $route->post('/users', function () {
        echo "group POST\n";
    }, 1, 'store');
}, [], 'group.');

echo $mf('router')->url('users.store').' '.$mf('router')->url('group.store')."\n";

?>
--EXPECT--
/users /users
POST
group POST
