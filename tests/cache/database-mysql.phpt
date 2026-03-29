--TEST--
Cache with database driver (MySQL)
--SKIPIF--
<?php
if (PHP_MAJOR_VERSION < 7) {
    die('Skip: PHP 7+ is required');
}
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

    $pdo = new PDO($dsn, $user, $pass);

    $cache = $mf('cache');
    $cache::setDriver('database', $pdo);
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
