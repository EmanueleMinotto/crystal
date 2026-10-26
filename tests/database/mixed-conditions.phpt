--TEST--
Conditions on columns combined with SQL conditions
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$mf(function () use ($mf) {
    $db = $mf('database');
    $db::setPdo(new PDO('sqlite::memory:'));

    $db->query('CREATE TABLE posts (id INTEGER PRIMARY KEY, status TEXT NOT NULL, views INTEGER NOT NULL)');

    foreach ([['published', 10], ['draft', 500], ['published', 99], ['published', 300]] as $post) {
        $db->create('posts', ['status' => $post[0], 'views' => $post[1]]);
    }

    $ids = function (array $rows) {
        return json_encode(array_column($rows, 'id'));
    };

    // the column first or last, positional placeholders
    echo $ids($db->select('posts', 'id', ['status' => 'published', 'views > ?' => 50], 'id'))."\n";
    echo $ids($db->select('posts', 'id', ['views > ?' => 50, 'status' => 'published'], 'id'))."\n";
    echo $ids($db->select('posts', 'id', ['status' => 'published', 'views > ? AND views < ?' => [50, 200]], 'id'))."\n";

    // named placeholders keep working with the columns
    echo $ids($db->select('posts', 'id', ['status' => 'published', 'views > :min' => ['min' => 50]], 'id'))."\n";

    echo json_encode($db->read('posts', ['status' => 'published', 'views > ?' => 200]))."\n";

    $db->update('posts', ['status' => 'archived'], ['status' => 'published', 'views < ?' => 50]);
    echo json_encode(array_column($db->select('posts', ['id', 'status'], null, 'id'), 'status'))."\n";

    $db->delete('posts', ['status' => 'published', 'views > ?' => 200]);
    echo $ids($db->select('posts', 'id', null, 'id'))."\n";

    // named and positional placeholders of the SQL conditions can't be combined
    try {
        $db->select('posts', 'id', ['views > ?' => 50, 'views < :max' => ['max' => 200]]);
    } catch (InvalidArgumentException $exception) {
        echo get_class($exception).': '.$exception->getMessage()."\n";
    }
});

?>
--EXPECT--
["3","4"]
["3","4"]
["3"]
["3","4"]
{"id":"4","status":"published","views":"300"}
["archived","draft","published","published"]
["1","2","3"]
InvalidArgumentException: Named and positional placeholders can't be combined in the same conditions.
