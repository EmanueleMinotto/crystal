--TEST--
CSRF protection
--FILE--
<?php

ob_start();
ini_set('session.save_path', sys_get_temp_dir());
ini_set('session.use_cookies', '0');

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/posts/42';
$_SERVER['SCRIPT_NAME'] = '/';

$csrf = $mf('csrf');
$auth = $mf('auth');

$token = $csrf->token();
var_dump(strlen($token), $token === $csrf->token());
var_dump($csrf->field() === '<input type="hidden" name="_token" value="' . $token . '">');

var_dump($csrf->check(), $csrf->check('wrong'), $csrf->check($token));

$_POST['_token'] = $token;
var_dump($csrf->check());
$_POST = [];
$_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
var_dump($csrf->check());

// the token changes on login
$auth->login(['id' => 1]);
var_dump($csrf->check(), $token !== $csrf->token());
unset($_SERVER['HTTP_X_CSRF_TOKEN']);

$route = '/posts/(?P<id>[0-9]+)';

$mf($route, $csrf->protect(function ($id) {
    echo "invalid token?\n";
}, function ($id) {
    echo "invalid token for $id\n";
}), 'POST', 0);

$mf($route, $csrf->protect(function () {
    echo "invalid token?\n";
}), 'POST', 1);

$mf($route, function () use ($csrf) {
    echo "status: " . http_response_code() . "\n";
    $_POST['_token'] = $csrf->token();
}, 'POST', 2);

$mf($route, $csrf->protect($auth->protect(function ($id) {
    echo "updated $id\n";
})), 'POST', 3);

// requests with a bearer token aren't checked
$mf($route, function () use ($auth) {
    $_POST = [];
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer secret';
    $auth->setTokenProvider(function ($token) {
        return 'secret' === $token ? ['id' => 2] : null;
    });
}, 'POST', 4);

$mf($route, $csrf->protect(function ($id) use ($auth) {
    echo "api: updated $id by " . $auth->id() . "\n";
    session_destroy();
}), 'POST', 5);

?>
--EXPECT--
int(64)
bool(true)
bool(true)
bool(false)
bool(false)
bool(true)
bool(true)
bool(true)
bool(false)
bool(true)
invalid token for 42
status: 403
updated 42
api: updated 42 by 2
