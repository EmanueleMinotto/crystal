--TEST--
Middlewares returning responses and auth/csrf middlewares
--FILE--
<?php

ob_start();
ini_set('session.save_path', sys_get_temp_dir());
ini_set('session.use_cookies', '0');

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/posts/42';
$_SERVER['SCRIPT_NAME'] = '/';

$middleware = $mf('middleware');
$auth = $mf('auth');
$csrf = $mf('csrf');
$mf('request')->setParams(['id' => '42']);

$auth->define('post:edit', function ($user, $request) {
    return 'admin' === $user['role'] || '42' !== $request->param('id');
});

$edit = $middleware->wrap(function () {
    echo "updated\n";
}, $csrf->middleware(), $auth->middleware('post:edit'));

$result = function ($response) {
    echo is_null($response) ? "callback invoked\n" : 'status: ' . $response->status() . "\n";
};

$result($middleware->wrap(function () {}, $auth->middleware())());

$auth->login(['id' => 1, 'role' => 'user']);
$result($middleware->wrap(function () {
    echo "authenticated\n";
}, $auth->middleware())());

$_POST['_token'] = $csrf->token();
$result($edit());

$auth->login(['id' => 2, 'role' => 'admin']);
$result($edit());

$_POST['_token'] = $csrf->token();
$result($edit());

// a middleware returning a response skips the next callbacks
$route = '/posts/(?P<id>[0-9]+)';

$mf($route, $middleware->wrap(function () {
    echo "not invoked\n";
}, function ($request, $next) use ($mf) {
    return $mf('response')->setStatus(429)->setBody("too many requests\n");
}), 'POST', 0);

$mf($route, function () {
    echo "skipped\n";
}, 'POST', 1);

?>
--EXPECT--
status: 401
authenticated
callback invoked
status: 403
status: 403
updated
callback invoked
too many requests
