--TEST--
Users remembered after the session ends
--FILE--
<?php

ob_start();
ini_set('session.save_path', sys_get_temp_dir());
ini_set('session.use_cookies', '0');

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/';

$db = $mf('database');
$db::setPdo(new PDO('sqlite::memory:'));
$db->query('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT NOT NULL, password TEXT NOT NULL)');

$auth = $mf('auth');
$db->create('users', ['id' => 1, 'email' => 'alice@example.com', 'password' => $auth->hash('secret')]);
$auth->useDatabase();
$auth->rememberWithDatabase();

$mf('listener')->on('auth:login', function ($event) {
    echo "login: " . $event->user['email'] . "\n";
});

// the session ends: the user is loaded from the cookie
$expire = function () use ($auth) {
    $_SESSION = [];
    $reflection = new ReflectionProperty($auth, 'user');
    if (PHP_VERSION_ID < 80100) {
        $reflection->setAccessible(true);
    }
    $reflection->setValue($auth, false);
};

try {
    $mf('auth')->setRememberCookie('crystal_remember');
    $auth->login($db->read('users', 1));
    var_dump(isset($_COOKIE['crystal_remember']));
} catch (Exception $exception) {
    echo $exception->getMessage() . "\n";
}

var_dump($auth->attempt('alice@example.com', 'secret', true));
$cookie = $_COOKIE['crystal_remember'];
var_dump((bool) preg_match('#^[a-f0-9]{24}:[a-f0-9]{64}$#', $cookie));

$row = $db->select('crystal_remember_tokens')[0];
var_dump($row['user_id'], $row['hash'] === hash('sha256', explode(':', $cookie)[1]));

$expire();
var_dump($auth->id());

// the token is replaced at each use
var_dump($cookie !== $_COOKIE['crystal_remember'], count($db->select('crystal_remember_tokens')));

// a used token is refused
$_COOKIE['crystal_remember'] = $cookie;
$expire();
var_dump($auth->check(), isset($_COOKIE['crystal_remember']));

// a wrong validator is refused and the token removed
$auth->attempt('alice@example.com', 'secret', true);
list($selector) = explode(':', $_COOKIE['crystal_remember']);
$_COOKIE['crystal_remember'] = $selector . ':' . str_repeat('0', 64);
$expire();
var_dump($auth->check(), $db->read('crystal_remember_tokens', $selector, 'selector'));

// expired tokens are refused
$auth->attempt('alice@example.com', 'secret', true);
$db->query('UPDATE crystal_remember_tokens SET expires = ?', [time() - 1]);
$expire();
var_dump($auth->check());

// logout removes the token
$auth->attempt('alice@example.com', 'secret', true);
list($selector) = explode(':', $_COOKIE['crystal_remember']);
$auth->logout();
var_dump(isset($_COOKIE['crystal_remember']), $db->read('crystal_remember_tokens', $selector, 'selector'));

session_destroy();

?>
--EXPECT--
login: alice@example.com
bool(false)
login: alice@example.com
bool(true)
bool(true)
string(1) "1"
bool(true)
login: alice@example.com
string(1) "1"
bool(true)
int(1)
bool(false)
bool(false)
login: alice@example.com
bool(false)
NULL
login: alice@example.com
bool(false)
login: alice@example.com
bool(false)
NULL
