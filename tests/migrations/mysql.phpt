--TEST--
Migrations with MySQL, where DDL statements are implicitly committed.
--SKIPIF--
<?php
if (!extension_loaded('pdo_mysql')) {
    die('Skip: pdo_mysql is required');
}
if (!getenv('CRYSTAL_MYSQL_DSN')) {
    die('Skip: CRYSTAL_MYSQL_DSN environment variable is not set');
}
?>
--FILE--
<?php
$mf = require_once(__DIR__.'/../../crystal.php');

$mf(function () use ($mf) {
    $dsn = getenv('CRYSTAL_MYSQL_DSN');
    $user = getenv('CRYSTAL_MYSQL_USER') ?: 'root';
    $pass = getenv('CRYSTAL_MYSQL_PASS') ?: '';

    $db = $mf('database');
    $db::setPdo(new PDO($dsn, $user, $pass));

    // Initial cleanup
    $db->query('DROP TABLE IF EXISTS crystal_migrations_test');
    $db->query('DROP TABLE IF EXISTS crystal_migrations_users');

    $migrations = $mf('migrations');
    $migrations->setTable('crystal_migrations_test');

    $migrations->add('1', function ($db) {
        $db->query('CREATE TABLE crystal_migrations_users (id INT PRIMARY KEY)');
    }, function ($db) {
        $db->query('DROP TABLE crystal_migrations_users');
    });

    echo json_encode($migrations->migrate())."\n";
    echo json_encode($migrations->applied())."\n";
    echo json_encode($migrations->rollback())."\n";
    echo json_encode($migrations->pending())."\n";
    echo json_encode($db->inTransaction())."\n";

    $db->query('DROP TABLE crystal_migrations_test');
});
?>
--EXPECT--
["1"]
["1"]
["1"]
["1"]
false
