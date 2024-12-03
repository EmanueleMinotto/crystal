--TEST--
Advanced usage of the where options.
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$mf(function () use ($mf) {
    $db = $mf('database');
    $pdo = new PDO('sqlite::memory:');

    $db::setPdo($pdo);

    $db->query('CREATE TABLE crystal_test (id INTEGER PRIMARY KEY, v1 TEXT NOT NULL, v2 TEXT NOT NULL)');

    for ($i = 1; $i < 4; $i++) {
        $db->create('crystal_test', [
            'id' => $i,
            'v1' => str_repeat('i', $i),
            'v2' => str_repeat('a', $i),
        ]);
    }

    echo "select all rows: " . json_encode($db->select('crystal_test'))."\n";
    echo "select only identifiers: " . json_encode($db->select('crystal_test', 'id'))."\n";
    echo "select only specific columns: " . json_encode($db->select('crystal_test', ['v1', 'v2']))."\n";

    echo "select by column and value: " . json_encode($db->select('crystal_test', '*', [
        'v1' => 'ii',
    ]))."\n";
    echo "select by custom query: " . json_encode($db->select('crystal_test', '*', [
        'v1 = ? OR v2 = ?' => ['ii', 'a'],
    ]))."\n";
    echo "select by multiple custom conditions: " . json_encode($db->select('crystal_test', '*', [
        'v1 = ? OR v2 = ?' => ['ii', 'a'],
        'v2 = ?' => 'a',
    ]))."\n";

    echo "select sorted: " . json_encode($db->select('crystal_test', ['id', 'v1'], [], 'id DESC'))."\n";
    echo "select limited: " . json_encode($db->select('crystal_test', ['id', 'v1'], [], null, 2))."\n";
});

?>
--EXPECT--
select all rows: [{"id":1,"v1":"i","v2":"a"},{"id":2,"v1":"ii","v2":"aa"},{"id":3,"v1":"iii","v2":"aaa"}]
select only identifiers: [{"id":1},{"id":2},{"id":3}]
select only specific columns: [{"v1":"i","v2":"a"},{"v1":"ii","v2":"aa"},{"v1":"iii","v2":"aaa"}]
select by column and value: [{"id":2,"v1":"ii","v2":"aa"}]
select by custom query: [{"id":1,"v1":"i","v2":"a"},{"id":2,"v1":"ii","v2":"aa"}]
select by multiple custom conditions: [{"id":1,"v1":"i","v2":"a"}]
select sorted: [{"id":3,"v1":"iii"},{"id":2,"v1":"ii"},{"id":1,"v1":"i"}]
select limited: [{"id":1,"v1":"i"},{"id":2,"v1":"ii"}]