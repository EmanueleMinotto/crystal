--TEST--
Create, retrieve, update and delete operations.
--SKIPIF--
<?php

if (PHP_MAJOR_VERSION < 7) {
    die('Skip: PHP 7+ is required');
}

?>
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$mf(function () use ($mf) {
    $db = $mf('database');
    $pdo = new PDO('sqlite::memory:');

    $db::setPdo($pdo);

    $db->query('CREATE TABLE crystal_test (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');

    $value = null;
    try {
        $value = $db->transactional(function () use ($db) {
            $db->create('crystal_test', [
                'id' => 1,
                'name' => 'test',
            ]);

            $db->read('not_existing', 1);

            return 'error';
        });
    } catch (Throwable $e) {
        echo $e->getMessage()."\n";
    }

    if ($db->read('crystal_test', 1)) {
        echo "row created!\n";
    }

    echo json_encode($value);
});

?>
--EXPECT--
SQLSTATE[HY000]: General error: 1 no such table: not_existing
null