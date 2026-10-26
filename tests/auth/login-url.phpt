--TEST--
Guests sent to the login page and back to the requested page
--FILE--
<?php

ob_start();
ini_set('session.save_path', sys_get_temp_dir());
ini_set('session.use_cookies', '0');

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/account?tab=orders';
$_SERVER['SCRIPT_NAME'] = '/';

$auth = $mf('auth');
$session = $mf('session');

$show = function ($response) {
    echo is_object($response) ? $response->status().' '.$response->header('Location') : 'no response, status '.http_response_code();
    echo "\n";
};

$page = $auth->protect(function () {
    return 'page';
});
$middleware = $auth->middleware();
$next = function () {
    return 'next';
};

// without a login URL guests receive a 401 response
$show($page());
$show($middleware($mf('request'), $next));
http_response_code(200);

$auth->setLoginUrl('/login');

// the requested URL of GET requests is stored
$show($page());
var_dump($session->get('crystal_intended'));
$show($middleware($mf('request'), $next));

// JSON requests and bearer tokens receive a 401 response
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$show($middleware($mf('request'), $next));
unset($_SERVER['HTTP_ACCEPT']);
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer abc';
$show($middleware($mf('request'), $next));
unset($_SERVER['HTTP_AUTHORIZATION']);

// other methods aren't stored
$session->remove('crystal_intended');
$_SERVER['REQUEST_METHOD'] = 'POST';
$show($page());
var_dump($session->get('crystal_intended'));

// after the login: the requested page, or the default one
$session->set('crystal_intended', '/account?tab=orders');
$show($auth->intended('/home'));
$show($auth->intended('/home'));
$show($auth->intended());

// only paths of this website
foreach (['//evil.example', '/\\evil.example', 'https://evil.example'] as $url) {
    $session->set('crystal_intended', $url);
    $show($auth->intended());
}

// authenticated users reach the page
$auth->login(['id' => 1]);
var_dump($page(), $middleware($mf('request'), $next));

?>
--EXPECT--
no response, status 401
401 
302 /login
string(19) "/account?tab=orders"
302 /login
401 
401 
302 /login
NULL
302 /account?tab=orders
302 /home
302 /
302 /
302 /
302 /
string(4) "page"
string(4) "next"
