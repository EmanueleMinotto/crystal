--TEST--
Cache with APCu driver
--SKIPIF--
<?php
if (PHP_MAJOR_VERSION < 7) {
    die('Skip: PHP 7+ is required');
}
if (!function_exists('apcu_enabled') || !apcu_enabled()) {
    die('Skip: APCu is required');
}
?>
--FILE--
<?php
$mf = require_once(__DIR__.'/../../crystal.php');

$mf(function () use ($mf) {
    $cache = $mf('cache');
    $cache::setDriver('apcu');
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
