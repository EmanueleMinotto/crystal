--TEST--
Apply and revert migrations registered with callbacks.
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

    // registered in a different order to check sorting
    $migrations->add('10', function ($db) {
        $db->query('ALTER TABLE users ADD COLUMN email TEXT');
    });
    $migrations->add('2', function ($db) {
        $db->query('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
    }, function ($db) {
        $db->query('DROP TABLE users');
    });

    echo json_encode($migrations->pending())."\n";
    echo json_encode($migrations->migrate('2'))."\n";
    echo json_encode($migrations->migrate())."\n";
    echo json_encode($migrations->migrate())."\n";
    echo json_encode($migrations->applied())."\n";

    $db->create('users', ['name' => 'foo', 'email' => 'foo@example.com']);
    echo json_encode($db->read('users', 1))."\n";

    try {
        $migrations->rollback();
    } catch (RuntimeException $e) {
        echo $e->getMessage()."\n";
    }

    try {
        $migrations->add('2', function () {
        });
    } catch (InvalidArgumentException $e) {
        echo $e->getMessage()."\n";
    }
});

$mf(function () use ($mf) {
    // services are shared, a different table tracks a separate history
    $migrations = $mf('migrations');
    $migrations->setTable('other_migrations');

    echo json_encode($migrations->pending())."\n";
    echo json_encode($migrations->applied())."\n";

    $migrations->setTable('crystal_migrations');
    $migrations->add('11', function ($db) {
        $db->query('CREATE TABLE posts (id INTEGER PRIMARY KEY)');
    }, function ($db) {
        $db->query('DROP TABLE posts');
    });

    echo json_encode($migrations->migrate())."\n";
    echo json_encode($migrations->rollback())."\n";
    echo json_encode($migrations->pending())."\n";
});

?>
--EXPECT--
["2","10"]
["2"]
["10"]
[]
["2","10"]
{"id":"1","name":"foo","email":"foo@example.com"}
Migration "10" can't be reverted
Migration "2" already registered
["2","10"]
[]
["11"]
["11"]
["11"]
