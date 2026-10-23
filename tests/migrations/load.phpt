--TEST--
Load migrations from a directory.
--SKIPIF--
<?php

if (PHP_MAJOR_VERSION < 7) {
    die('Skip: PHP 7+ is required');
}

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
    $migrations->load(__DIR__.'/fixtures');

    echo json_encode($migrations->migrate())."\n";
    echo json_encode($db->select('users', 'name'))."\n";

    try {
        $migrations->load(__DIR__.'/not-existing');
    } catch (InvalidArgumentException $e) {
        echo "invalid directory\n";
    }
});

?>
--EXPECT--
["20240101000000_create_users","20240102000000_seed_users"]
[{"name":"foo"}]
invalid directory
