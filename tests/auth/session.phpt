--TEST--
Authentication with credentials stored in the session
--FILE--
<?php

ob_start();
ini_set('session.save_path', sys_get_temp_dir());
ini_set('session.use_cookies', '0');

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/';

$auth = $mf('auth');
$users = [
    1 => ['id' => 1, 'email' => 'alice@example.com', 'password' => $auth->hash('secret')],
];

$auth->setProvider(function ($id) use (&$users) {
    return isset($users[$id]) ? $users[$id] : null;
}, function ($email) use (&$users) {
    foreach ($users as $user) {
        if ($user['email'] === $email) {
            return $user;
        }
    }
});

$mf('listener')->on('auth:failed', function ($event) {
    echo "failed: " . $event->identifier . "\n";
});
$mf('listener')->on('auth:login', function ($event) {
    echo "login: " . $event->user['email'] . "\n";
});
$mf('listener')->on('auth:logout', function ($event) {
    echo "logout: " . $event->user['email'] . "\n";
});

var_dump($auth->check());
var_dump($auth->attempt('alice@example.com', 'wrong'));
var_dump($auth->attempt('bob@example.com', 'secret'));

$sessionId = session_id();

var_dump($auth->attempt('alice@example.com', 'secret'));
var_dump($auth->check(), $auth->id());
var_dump($_SESSION['crystal_auth']);
var_dump($sessionId !== session_id());

// the user is loaded again from the session
session_write_close();
$sessionId = session_id();
$_SESSION = [];

$reflection = new ReflectionProperty($auth, 'user');
if (PHP_VERSION_ID < 80100) {
    $reflection->setAccessible(true);
}
$reflection->setValue($auth, false);

session_id($sessionId);
var_dump($auth->user()['email']);

// users removed after the login aren't authenticated anymore
unset($users[1]);
$reflection->setValue($auth, false);
var_dump($auth->check(), isset($_SESSION['crystal_auth']));

$users[1] = ['id' => 1, 'email' => 'alice@example.com', 'password' => $auth->hash('secret')];
$auth->login($users[1]);
$auth->logout();
var_dump($auth->check(), isset($_SESSION['crystal_auth']));

session_destroy();

?>
--EXPECT--
bool(false)
failed: alice@example.com
bool(false)
failed: bob@example.com
bool(false)
login: alice@example.com
bool(true)
bool(true)
int(1)
int(1)
bool(true)
string(17) "alice@example.com"
bool(false)
bool(false)
login: alice@example.com
logout: alice@example.com
bool(false)
bool(false)
