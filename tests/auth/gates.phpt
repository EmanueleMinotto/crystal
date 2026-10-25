--TEST--
Gates authorization from the command line
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$mf(function () use ($mf) {
    $auth = $mf('auth');

    $auth->define('post:edit', function ($user, $post) {
        return $user->id === $post['author_id'];
    });
    $auth->define('admin', function ($user) {
        return $user->admin;
    });

    $post = ['author_id' => 1];

    // guests are never authorized
    var_dump($auth->can('post:edit', $post));

    $user = new stdClass;
    $user->id = 1;
    $user->admin = false;
    $auth->login($user);

    var_dump($auth->check(), $auth->id());
    var_dump($auth->can('post:edit', $post));
    var_dump($auth->can('post:edit', ['author_id' => 2]));
    var_dump($auth->can('admin'));

    try {
        $auth->can('undefined');
    } catch (Exception $exception) {
        echo $exception->getMessage() . "\n";
    }

    try {
        $auth->login(['name' => 'no identifier']);
    } catch (Exception $exception) {
        echo $exception->getMessage() . "\n";
    }

    $auth->logout();
    var_dump($auth->check(), $auth->id());
});

?>
--EXPECT--
bool(false)
bool(true)
int(1)
bool(true)
bool(false)
bool(false)
Gate "undefined" not defined.
The user has no "id" field.
bool(false)
NULL
