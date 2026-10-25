--TEST--
Sessions aren't started to read values of requests without the session cookie
--FILE--
<?php

ob_start();
ini_set('session.save_path', sys_get_temp_dir());
ini_set('session.use_cookies', '1');
ini_set('session.use_only_cookies', '1');

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/';

$session = $mf('session');
$auth = $mf('auth');
$auth->setProvider(function ($id) {
    return ['id' => $id];
});

var_dump($session->get('missing', 'default'), $session->has('missing'), $auth->check());
$session->remove('missing');
var_dump(PHP_SESSION_ACTIVE === session_status());

// writing a value starts the session
$session->set('key', 'value');
var_dump(PHP_SESSION_ACTIVE === session_status(), $session->get('key'));
session_write_close();

// requests with the session cookie start it to read the values
$_COOKIE[session_name()] = session_id();
var_dump($session->get('key'), PHP_SESSION_ACTIVE === session_status());
session_destroy();

?>
--EXPECT--
string(7) "default"
bool(false)
bool(false)
bool(false)
bool(true)
string(5) "value"
string(5) "value"
bool(true)
