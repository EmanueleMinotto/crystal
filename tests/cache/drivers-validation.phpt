--TEST--
Cache memcached and redis drivers validate their configuration
--FILE--
<?php
$mf = require_once(__DIR__.'/../../crystal.php');

$mf(function () use ($mf) {
    $cache = $mf('cache');

    foreach (['memcached', 'redis'] as $driver) {
        try {
            $cache::setDriver($driver, 'localhost');
            echo "no exception\n";
        } catch (\RuntimeException $e) {
            echo $e->getMessage() === "The $driver extension is not available" ? "ok\n" : "ko\n";
        } catch (\InvalidArgumentException $e) {
            echo $e->getMessage() === "$driver driver requires a ".ucfirst($driver)." instance as second argument" ? "ok\n" : "ko\n";
        }
    }
});
?>
--EXPECT--
ok
ok
