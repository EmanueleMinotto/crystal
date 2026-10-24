--TEST--
Cache with array driver
--FILE--
<?php
$mf = require_once(__DIR__.'/../../crystal.php');

$mf(function () use ($mf) {
    $cache = $mf('cache');
    $cache::setDriver('array');
    $key = 'test_key';
    $value = 'test_value';

    // Initial cleanup
    $cache->delete($key);

    // Test set and get
    $cache->set($key, $value);
    echo $cache->get($key) . "\n";

    // Test has
    echo ($cache->has($key) ? '1' : '0') . "\n";

    // Test delete
    $cache->delete($key);
    echo ($cache->has($key) ? '1' : '0') . "\n";

    // Test get with default
    echo $cache->get($key, 'default') . "\n";

    // Test setMultiple and getMultiple
    $cache->setMultiple(['a' => 1, 'b' => 2]);
    $results = $cache->getMultiple(['a', 'b', 'c'], 'none');
    echo $results['a'] . "," . $results['b'] . "," . $results['c'] . "\n";

    // Test deleteMultiple
    $cache->deleteMultiple(['a', 'b']);
    echo ($cache->has('a') ? '1' : '0') . "," . ($cache->has('b') ? '1' : '0') . "\n";

    // Test clear
    $cache->set('to_clear', 'yes');
    $cache->clear();
    echo ($cache->has('to_clear') ? '1' : '0') . "\n";

    // Test namespaces
    $users = $cache->withNamespace('users');
    $admins = $users->withNamespace('admins');
    $cache->set('name', 'root');
    $users->set('name', 'user');
    $admins->set('name', 'admin');
    echo $cache->get('name') . "," . $users->get('name') . "," . $admins->get('name') . "\n";

    // Test namespace clear
    $users->clear();
    echo $cache->get('name', 'none') . "," . $users->get('name', 'none') . "," . $admins->get('name', 'none') . "\n";

    // Test stored objects are copies
    $object = new stdClass();
    $object->value = 'before';
    $cache->set('object', $object);
    $object->value = 'after';
    echo $cache->get('object')->value . "\n";
});
?>
--EXPECT--
test_value
1
0
default
1,2,none
0,0
0
root,user,admin
root,none,none
before
