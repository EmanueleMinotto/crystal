--TEST--
A failing migration is reverted and not marked as applied.
--SKIPIF--
<?php

if (!extension_loaded('pdo_sqlite')) {
    die('Skip: pdo_sqlite is required');
}

?>
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$mf(function () use ($mf) {
    $db = $mf('database');
    $db::setPdo(new PDO('sqlite::memory:'));

    $migrations = $mf('migrations');

    $migrations->add('1', function ($db) {
        $db->query('CREATE TABLE users (id INTEGER PRIMARY KEY)');
    });
    $migrations->add('2', function ($db) {
        $db->query('CREATE TABLE posts (id INTEGER PRIMARY KEY)');
        $db->query('SELECT * FROM not_existing');
    });

    try {
        $migrations->migrate();
    } catch (Throwable $e) {
        echo $e->getMessage()."\n";
    }

    echo json_encode($migrations->applied())."\n";
    echo json_encode($migrations->pending())."\n";
    echo json_encode($db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name IN ('users', 'posts')"))."\n";
    echo json_encode($db->inTransaction())."\n";
});

?>
--EXPECT--
SQLSTATE[HY000]: General error: 1 no such table: not_existing
["1"]
["2"]
[{"name":"users"}]
false
