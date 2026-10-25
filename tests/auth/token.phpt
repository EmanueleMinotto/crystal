--TEST--
Users authenticated by bearer tokens
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$mf(function () use ($mf) {
    $db = $mf('database');
    $db::setPdo(new PDO('sqlite::memory:'));
    $db->query('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT NOT NULL, password TEXT NOT NULL, api_token TEXT)');

    $auth = $mf('auth');
    $token = $auth->generateToken();
    var_dump(strlen($token), $auth->hashToken($token) === hash('sha256', $token));

    $db->create('users', [
        'id' => 1,
        'email' => 'alice@example.com',
        'password' => $auth->hash('secret'),
        'api_token' => $auth->hashToken($token),
    ]);
    $auth->useDatabase('users', 'email', 'api_token');

    $reset = function () use ($auth) {
        $reflection = new ReflectionProperty($auth, 'user');
        if (PHP_VERSION_ID < 80100) {
            $reflection->setAccessible(true);
        }
        $reflection->setValue($auth, false);
    };

    var_dump($auth->check(), $auth->viaToken());

    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
    $reset();
    var_dump($auth->user()['email'], $auth->viaToken());

    // invalid tokens don't fall back to the session
    $auth->login($db->read('users', 1));
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer invalid';
    $reset();
    var_dump($auth->check(), $auth->viaToken());

    // other authorization schemes are ignored
    $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('alice:secret');
    $reset();
    var_dump($auth->id(), $auth->viaToken());
});

?>
--EXPECT--
int(64)
bool(true)
bool(false)
bool(false)
string(17) "alice@example.com"
bool(true)
bool(false)
bool(false)
string(1) "1"
bool(false)
