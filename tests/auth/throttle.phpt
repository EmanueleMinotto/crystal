--TEST--
Failed login attempts are throttled
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$mf(function () use ($mf) {
    $mf('cache')::setDriver('array');

    $auth = $mf('auth');
    $user = ['id' => 1, 'email' => 'alice@example.com', 'password' => $auth->hash('secret')];

    $auth->setProvider(function () use ($user) {
        return $user;
    }, function ($email) use ($user) {
        return $email === $user['email'] ? $user : null;
    });
    $auth->throttle(2, 60);

    $mf('listener')->on('auth:lockout', function ($event) {
        echo "lockout: " . $event->identifier . "\n";
    });

    var_dump($auth->lockedFor('alice@example.com'));
    var_dump($auth->attempt('alice@example.com', 'wrong'));

    // a successful login resets the attempts
    var_dump($auth->attempt('alice@example.com', 'secret'));
    var_dump($auth->attempt('alice@example.com', 'wrong'));
    var_dump($auth->lockedFor('alice@example.com'));
    var_dump($auth->attempt('ALICE@example.com', 'wrong'));
    var_dump($auth->lockedFor('alice@example.com') > 55);

    // even valid credentials are refused while locked
    var_dump($auth->attempt('alice@example.com', 'secret'));

    // other identifiers and IP addresses aren't locked
    var_dump($auth->lockedFor('bob@example.com'));
    $_SERVER['REMOTE_ADDR'] = '127.0.0.2';
    var_dump($auth->attempt('alice@example.com', 'secret'));
});

?>
--EXPECT--
int(0)
bool(false)
bool(true)
bool(false)
int(0)
bool(false)
bool(true)
lockout: alice@example.com
bool(false)
int(0)
bool(true)
