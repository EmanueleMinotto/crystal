--TEST--
Conditions of read, update and delete
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$mf(function () use ($mf) {
    $db = $mf('database');
    $db::setPdo(new PDO('sqlite::memory:'));

    $db->query('CREATE TABLE t (id INTEGER PRIMARY KEY, status TEXT NOT NULL, score INTEGER NOT NULL)');

    foreach (['pending', 'done', 'pending'] as $i => $status) {
        $db->create('t', ['status' => $status, 'score' => $i]);
    }

    $rows = function () use ($db) {
        echo json_encode($db->select('t', '*', null, 'id')) . "\n";
    };

    // the same column in the values and in the conditions
    $db->update('t', ['status' => 'active'], ['status' => 'pending']);
    $rows();

    // conditions with positional placeholders
    $db->update('t', ['score' => 10], ['score > ?' => 0, 'status = ?' => 'active']);
    $rows();

    // updating the key used by the conditions
    $db->update('t', ['id' => 4], 2);
    $rows();

    echo json_encode($db->read('t', ['status' => 'active', 'score' => 10])) . "\n";
    var_dump($db->read('t', ['status' => 'missing']));

    $db->delete('t', ['status' => 'active']);
    $rows();

    // values matched by the key keep working
    $db->delete('t', [4]);
    $rows();
});

?>
--EXPECT--
[{"id":"1","status":"active","score":"0"},{"id":"2","status":"done","score":"1"},{"id":"3","status":"active","score":"2"}]
[{"id":"1","status":"active","score":"0"},{"id":"2","status":"done","score":"1"},{"id":"3","status":"active","score":"10"}]
[{"id":"1","status":"active","score":"0"},{"id":"3","status":"active","score":"10"},{"id":"4","status":"done","score":"1"}]
{"id":"3","status":"active","score":"10"}
NULL
[{"id":"4","status":"done","score":"1"}]
[]
