<?php

/**
 * Shared checks of the SQL generated for different databases,
 * using reserved words as table and column names.
 */
return function ($mf, PDO $pdo) {
    $db = $mf('database');
    $db::setPdo($pdo);

    $order = $db->quoteIdentifier('order');
    $db->query('DROP TABLE IF EXISTS '.$order);
    $db->query('DROP TABLE IF EXISTS '.$db->quoteIdentifier('crystal_migrations_portability'));
    $db->query('DROP TABLE IF EXISTS '.$db->quoteIdentifier('crystal_remember_portability'));
    $db->query(sprintf(
        'CREATE TABLE %s (%s INT NOT NULL PRIMARY KEY, %s VARCHAR(20) NOT NULL)',
        $order,
        $db->quoteIdentifier('id'),
        $db->quoteIdentifier('group')
    ));

    $created = [];

    for ($i = 1; $i < 4; $i++) {
        $created[] = $db->create('order', ['id' => $i, 'group' => 'g'.$i]);
    }

    echo 'create: '.json_encode($created)."\n";

    echo 'select by column: '.json_encode($db->select('order', ['id', 'group'], ['group' => 'g2']))."\n";
    echo 'select in: '.json_encode($db->select('order', 'id', [1, 3], 'id'))."\n";
    echo 'read: '.$db->read('order', 3)['group']."\n";
    echo 'read by column: '.$db->read('order', 'g1', 'group')['id']."\n";

    $db->update('order', ['group' => 'updated'], [1, 2], 'id');
    echo 'update in: '.json_encode($db->select('order', 'group', null, 'id'))."\n";

    $db->delete('order', [], 'id');
    echo 'delete nothing: '.count($db->select('order'))."\n";
    $db->delete('order', [1, 3], 'id');
    echo 'delete in: '.json_encode($db->select('order', 'id'))."\n";

    // migrations tracking table
    $migrations = $mf('migrations');
    $migrations->setTable('crystal_migrations_portability');
    $migrations->add('1', function ($db) {
        $db->create('order', ['id' => 10, 'group' => 'migrated']);
    }, function ($db) {
        $db->delete('order', 10);
    });
    echo 'migrate: '.json_encode($migrations->migrate()).' '.json_encode($migrations->applied())."\n";
    echo 'rollback: '.json_encode($migrations->rollback()).' '.count($db->select('order'))."\n";

    // remember-me tokens table
    $auth = $mf('auth');
    $auth->setProvider(function ($id) {
        return ['id' => $id];
    });
    $auth->rememberWithDatabase('crystal_remember_portability');
    $auth->login(['id' => 1], true);
    echo 'remember: '.count($db->select('crystal_remember_portability'))."\n";

    // cache: upsert and values with NUL bytes (like private properties)
    $cache = $mf('cache');
    $cache::setDriver('database', $pdo);
    $value = new ArrayObject(['nested' => "a\0b"]);
    $cache->set('portability', $value);
    $cache->set('portability', $value, 60);
    echo 'cache: '.json_encode($cache->get('portability')['nested'] === "a\0b").' '.json_encode($cache->has('portability'))."\n";
    $cache->delete('portability');
};
