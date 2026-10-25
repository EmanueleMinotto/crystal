--TEST--
Users loaded from the database, with automatic rehash
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$mf(function () use ($mf) {
    $db = $mf('database');
    $db::setPdo(new PDO('sqlite::memory:'));
    $db->query('CREATE TABLE accounts (uid INTEGER PRIMARY KEY, username TEXT NOT NULL, pass TEXT NOT NULL)');

    $outdated = password_hash('secret', PASSWORD_BCRYPT, ['cost' => 4]);
    $db->create('accounts', ['uid' => 7, 'username' => 'alice', 'pass' => $outdated]);

    $auth = $mf('auth');
    $auth->setFields('uid', 'pass');
    $auth->useDatabase('accounts', 'username');

    try {
        $mf('auth')->setProvider(function () {});
        $mf('auth')->attempt('alice', 'secret');
    } catch (Exception $exception) {
        echo $exception->getMessage() . "\n";
    }

    $auth->useDatabase('accounts', 'username');

    var_dump($auth->attempt('alice', 'wrong'));
    var_dump($auth->attempt('nobody', 'secret'));
    var_dump($auth->attempt('alice', 'secret'));
    var_dump($auth->id());

    $hash = $db->read('accounts', 7, 'uid')['pass'];
    var_dump($hash !== $outdated, $auth->verify('secret', $hash), $auth->needsRehash($hash));
});

?>
--EXPECT--
No provider defined to find users by identifier.
bool(false)
bool(false)
bool(true)
string(1) "7"
bool(true)
bool(true)
bool(false)
