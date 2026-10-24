--TEST--
Cache with chain driver
--FILE--
<?php
$mf = require_once(__DIR__.'/../../crystal.php');

// custom driver exposing its storage, to inspect each layer of the chain
$layer = function () {
    return new class () {
        public $store = [];
        public $ttls = [];

        public function get($key, $default = null)
        {
            return array_key_exists($key, $this->store) ? $this->store[$key] : $default;
        }

        public function set($key, $value, $ttl = null)
        {
            $this->store[$key] = $value;
            $this->ttls[$key] = $ttl;

            return true;
        }

        public function delete($key)
        {
            unset($this->store[$key]);

            return true;
        }

        public function clear()
        {
            $this->store = [];

            return true;
        }

        public function has($key)
        {
            return array_key_exists($key, $this->store);
        }
    };
};

$tmpDir = sys_get_temp_dir() . '/crystal_cache_chain_' . getmypid();
mkdir($tmpDir, 0777, true);

$mf(function () use ($mf, $layer, $tmpDir) {
    $cache = $mf('cache');
    $fast = $layer();
    $slow = $layer();
    $cache::setDriver('chain', [$fast, $slow]);

    // set writes on every layer
    $cache->set('key', 'value', 100);
    echo count($fast->store) . ',' . count($slow->store) . "\n";

    // get reads from the first layer that has the value
    $fast->store['key']['value'] = 'from fast';
    echo $cache->get('key') . "\n";

    // a value found in a slower layer is copied in the faster ones,
    // with the remaining time-to-live
    $fast->clear();
    echo $cache->get('key') . "\n";
    echo (isset($fast->store['key']) ? '1' : '0') . ',' . ($fast->ttls['key'] <= 100 && $fast->ttls['key'] > 95 ? 'ttl ok' : 'ttl ko') . "\n";

    // expired values are misses
    $slow->store['expired'] = ['value' => 'old', 'expires' => time() - 1];
    echo $cache->get('expired', 'none') . "," . (isset($fast->store['expired']) ? '1' : '0') . "\n";

    // values not stored by the chain are misses
    $slow->store['raw'] = 'raw';
    echo $cache->get('raw', 'none') . "\n";

    // has checks every layer
    $fast->delete('key');
    echo ($cache->has('key') ? '1' : '0') . "\n";

    // delete and clear act on every layer
    $cache->delete('key');
    echo count($fast->store) . ',' . (isset($slow->store['key']) ? '1' : '0') . "\n";
    $cache->clear();
    echo count($fast->store) . ',' . count($slow->store) . "\n";

    // namespaces can't be cleared when a layer doesn't support it
    try {
        $cache->withNamespace('ns')->clear();
    } catch (\RuntimeException $e) {
        echo $e->getMessage() . "\n";
    }

    // built-in drivers, as names or [name, config] pairs, including nested chains
    $cache::setDriver('chain', ['array', ['chain', [['filename', $tmpDir]]]]);
    $users = $cache->withNamespace('users');
    $cache->set('name', 'root');
    $users->set('name', 'user', 60);
    $users->clear();
    echo $cache->get('name') . ',' . $users->get('name', 'none') . "\n";
    echo count(glob($tmpDir . '/*.cache')) . "\n";
    $cache->clear();

    // invalid configurations
    foreach ([null, [], ['unknown'], [new stdClass()]] as $config) {
        try {
            $cache::setDriver('chain', $config);
        } catch (\InvalidArgumentException $e) {
            echo $e->getMessage() . "\n";
        }
    }

    rmdir($tmpDir);
});
?>
--EXPECT--
1,1
from fast
value
1,ttl ok
none,0
none
1
0,0
0,0
The cache driver does not support clearing a namespace
root,none
1
chain driver requires a non-empty array of drivers as second argument
chain driver requires a non-empty array of drivers as second argument
Unknown cache driver: unknown
Custom driver is missing methods: get, set, delete, has, clear
