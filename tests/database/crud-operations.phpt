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

    echo json_encode($db->create('crystal_test', [
        'id' => 1,
        'name' => 'foo',
    ]))."\n";

    echo json_encode($db->read('crystal_test', 1))."\n";

    echo json_encode($db->update('crystal_test', [
        'name' => 'bar',
    ], 1))."\n";

    $data = $db->read('crystal_test', 1);
    if ($data['name'] !== 'bar') {
        echo "wrong update!";
    }

    echo json_encode($db->delete('crystal_test', 1))."\n";

    $data = $db->read('crystal_test', 1);
    if (!empty($data['name'])) {
        echo "wrong delete!";
    }
});

?>
--EXPECT--
1
{"id":"1","name":"foo"}
true
true