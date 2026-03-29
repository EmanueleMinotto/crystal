--TEST--
Cache setDriver('apcu') throws when APCu is not available
--SKIPIF--
<?php
if (PHP_MAJOR_VERSION < 7) {
    die('Skip: PHP 7+ is required');
}
if (function_exists('apcu_enabled') && apcu_enabled()) {
    die('Skip: APCu is available; this test requires APCu to be absent');
}
?>
--FILE--
<?php
$mf = require_once(__DIR__.'/../../crystal.php');

$mf(function () use ($mf) {
    $cache = $mf('cache');

    // Explicit setDriver('apcu') must throw when APCu is not installed
    try {
        $cache::setDriver('apcu');
        echo "no exception\n";
    } catch (\RuntimeException $e) {
        echo "RuntimeException: " . $e->getMessage() . "\n";
    }

    // Implicit lazy-init (no setDriver called) must also throw
    try {
        $cache->get('key');
        echo "no exception\n";
    } catch (\RuntimeException $e) {
        echo "RuntimeException: " . $e->getMessage() . "\n";
    }
});
?>
--EXPECT--
RuntimeException: APCu is not available or not enabled
RuntimeException: APCu is not available or not enabled
