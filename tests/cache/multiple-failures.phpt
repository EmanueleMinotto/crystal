--TEST--
setMultiple and deleteMultiple don't stop at the first failure
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

// like APCu, deleting a missing key fails
$driver = new class () {
    public $items = [];

    public function get($key, $default = null) { return $this->items[$key] ?? $default; }
    public function set($key, $value, $ttl = null) { if ('fail' === $key) { return false; } $this->items[$key] = $value; return true; }
    public function delete($key) { $found = isset($this->items[$key]); unset($this->items[$key]); return $found; }
    public function has($key) { return isset($this->items[$key]); }
    public function clear() { $this->items = []; return true; }
};

$cache = $mf('cache');
$cache::setDriver($driver);

var_dump($cache->setMultiple(['fail' => 1, 'a' => 2, 'b' => 3]), $cache->has('a'), $cache->has('b'));
var_dump($cache->deleteMultiple(['missing', 'a', 'b']), $cache->has('a'), $cache->has('b'));

?>
--EXPECT--
bool(false)
bool(true)
bool(true)
bool(false)
bool(false)
bool(false)
