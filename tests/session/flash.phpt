--TEST--
Flash values, pulled values and the values of a form
--FILE--
<?php

ob_start();
ini_set('session.save_path', sys_get_temp_dir());
ini_set('session.use_cookies', '0');

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/settings';
$_SERVER['SCRIPT_NAME'] = '/';

$session = $mf('session');

// a new session object is the session of the next request
$next = function () use ($session) {
    session_write_close();
    $_SESSION = [];
    $class = get_class($session);

    return new $class();
};

// request 1: the form is sent
$session->flash('notice', 'Settings saved.');
$session->flashInput(['email' => 'alice@example.com', 'password' => 'secret', 'new_password' => 'secret']);
$session->set('cart', [42]);
var_dump($session->getFlash('notice'));

// request 2: the values are available after the redirect
$session = $next();
var_dump($session->getFlash('notice'), $session->old('email'), $session->old('password'), $session->old('new_password', 'none'));
var_dump($session->pull('cart'), $session->has('cart'));

// request 3: they're gone
$session = $next();
var_dump($session->getFlash('notice', 'gone'), $session->old('email'), isset($_SESSION['crystal_flash']));

// a session without flash values isn't changed
$session = $next();
var_dump(array_keys($_SESSION));

?>
--EXPECT--
string(15) "Settings saved."
string(15) "Settings saved."
string(17) "alice@example.com"
NULL
string(4) "none"
array(1) {
  [0]=>
  int(42)
}
bool(false)
string(4) "gone"
NULL
bool(false)
array(0) {
}
