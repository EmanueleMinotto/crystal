--TEST--
Protected routes with named subpatterns
--FILE--
<?php

ob_start();
ini_set('session.save_path', sys_get_temp_dir());
ini_set('session.use_cookies', '0');

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/posts/42/edit';
$_SERVER['SCRIPT_NAME'] = '/';

$auth = $mf('auth');

$auth->define('post:edit', function ($user, $action, $id) {
    return 'admin' === $user['role'] || '42' !== $id;
});

$route = '/posts/(?P<id>[0-9]+)/(?P<action>[a-z]+)';

$mf($route, $auth->protect(function ($action, $id) {
    echo "guest allowed?\n";
}, function ($action, $id) {
    echo "guest: login required to $action $id\n";
}), 'GET', 0);

$mf($route, function () use ($auth) {
    $auth->login(['id' => 1, 'role' => 'user']);
}, 'GET', 1);

$mf($route, $auth->protect(function ($action, $id) {
    echo "user: $action $id\n";
}), 'GET', 2);

$mf($route, $auth->protect($auth->protect(function ($action, $id) {
    echo "forbidden?\n";
}), function ($action, $id) use ($auth) {
    echo "user: forbidden to $action $id\n";
}, 'post:edit'), 'GET', 3);

$mf($route, $auth->protect(function ($action, $id) {
    echo "forbidden?\n";
}, null, 'post:edit'), 'GET', 4);

$mf($route, function () use ($auth) {
    echo "status: " . http_response_code() . "\n";
    $auth->login(['id' => 2, 'role' => 'admin']);
}, 'GET', 5);

$mf($route, $auth->protect(function ($action, $id) {
    echo "admin: $action $id\n";
    session_destroy();
}, null, 'post:edit'), 'GET', 6);

?>
--EXPECT--
guest: login required to edit 42
user: edit 42
user: forbidden to edit 42
status: 403
admin: edit 42
