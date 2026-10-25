The cache is provided as a [PSR-16](https://www.php-fig.org/psr/psr-16/)-like object supporting multiple storage backends (drivers).

## Accessing the Cache

The cache is available via the main callable, just like the logger:

```php
$mf(function () use ($mf) {
    $cache = $mf('cache');

    // Store a value
    $cache->set('user_42', ['name' => 'Alice'], 600);

    // Retrieve a value
    $user = $cache->get('user_42');

    // Check if a key exists
    if ($cache->has('user_42')) {
        // ...
    }

    // Delete a value
    $cache->delete('user_42');
});
```

## Choosing a Driver

Call `setDriver` before the first cache operation. If no driver is set, APCu is used by default.

### APCu (default)

Requires the [APCu](https://www.php.net/manual/en/book.apcu.php) PHP extension.

```php
$cache = $mf('cache');
$cache::setDriver('apcu');
```

### Array

Stores entries in memory, for the current request only. Useful in tests or as the first level of a [chain](#chain).

```php
$cache = $mf('cache');
$cache::setDriver('array');
```

Values are stored serialized, so objects changed after `set` don't change the cached value.

### Filesystem

Stores each cache entry as a file inside the given directory. The directory must exist and be writable.

```php
$cache = $mf('cache');
$cache::setDriver('filename', '/path/to/cache/dir');
```

Each entry is stored as `{key}.cache`. TTL is enforced on read.

### Database

Uses a PDO connection (MySQL, MariaDB, PostgreSQL or SQLite). The table `crystal_cache` is created automatically if it does not exist.

```php
$pdo = new PDO('mysql:host=localhost;dbname=myapp', 'user', 'pass');
$cache = $mf('cache');
$cache::setDriver('database', $pdo);
```

```php
$pdo = new PDO('sqlite:/path/to/cache.db');
$cache = $mf('cache');
$cache::setDriver('database', $pdo);
```

### Memcached

Requires the [memcached](https://www.php.net/manual/en/book.memcached.php) PHP extension and a configured `Memcached` instance.

```php
$memcached = new Memcached();
$memcached->addServer('127.0.0.1', 11211);

$cache = $mf('cache');
$cache::setDriver('memcached', $memcached);
```

`clear()` flushes every server of the instance.

### Redis

Requires the [redis](https://github.com/phpredis/phpredis) PHP extension and a connected `Redis` instance.

```php
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

$cache = $mf('cache');
$cache::setDriver('redis', $redis);
```

`clear()` flushes the selected Redis database.

### Chain

Combines several drivers, from the fastest to the slowest. Each driver is a name, a `[name, config]` pair or a custom driver object, chains can be nested too.

```php
$cache = $mf('cache');
$cache::setDriver('chain', [
    'array',
    ['redis', $redis],
    ['database', $pdo],
]);
```

-   `get` returns the value from the first driver that has it and copies it in the previous drivers, with the remaining time-to-live
-   `set`, `delete` and `clear` act on every driver
-   `has` is true when any driver has the key

Values are stored in each driver together with their expiration timestamp, so the drivers of a chain shouldn't be read directly.

### Custom

Pass any object that implements `get`, `set`, `delete`, `has`, and `clear`. An `\InvalidArgumentException` is thrown at configuration time if any method is missing.

```php
$myDriver = new class () {
    private $store = [];

    public function get($key, $default = null) { return $this->store[$key] ?? $default; }
    public function set($key, $value, $ttl = null) { $this->store[$key] = $value; return true; }
    public function delete($key) { unset($this->store[$key]); return true; }
    public function clear() { $this->store = []; return true; }
    public function has($key) { return isset($this->store[$key]); }
};

$cache = $mf('cache');
$cache::setDriver($myDriver);
```

To support [namespaces](#namespaces) `clear()`, a custom driver can also implement `clearPrefix($prefix)`, deleting every key starting with `$prefix`.

## Basic Operations

The cache object supports the following methods:

-   `get($key, $default = null)`: Retrieve a value by key. Returns `$default` if the key does not exist.
-   `set($key, $value, $ttl = null)`: Store a value by key, with optional time-to-live (in seconds).
-   `delete($key)`: Remove a value by key.
-   `clear()`: Clear all cache entries.
-   `getMultiple($keys, $default = null)`: Retrieve multiple values by an iterable of keys.
-   `setMultiple($values, $ttl = null)`: Store multiple key-value pairs.
-   `deleteMultiple($keys)`: Remove multiple keys.
-   `has($key)`: Check if a key exists.
-   `withNamespace($namespace)`: Get a cache instance isolated in a sub namespace.

## Namespaces

`withNamespace($namespace)` returns a cache instance whose keys are isolated in a namespace, sharing the same driver. Namespaces can be nested.

```php
$cache = $mf('cache');
$users = $cache->withNamespace('users');
$admins = $users->withNamespace('admins');

$cache->set('name', 'root');
$users->set('name', 'Alice');
$admins->set('name', 'Bob');

$users->get('name'); // Alice

// clears users and users > admins, root keys and other namespaces are kept
$users->clear();
```

Namespaces follow the same rules as keys. Internally keys are prefixed by the namespaces separated by `:` (e.g. `users:admins:name`), a character not allowed in keys, so they can't collide.

`clear()` on a namespace is supported by every built-in driver. Memcached can't list its keys, so its namespaces are versioned: clearing a namespace changes its version and the previous keys become unreachable, until memcached evicts them. Custom drivers need a `clearPrefix` method, otherwise a `\RuntimeException` is thrown.

## Key Requirements

-   Keys must be non-empty strings matching `/^[A-Za-z0-9_.-]+$/`.
-   Values can be any serializable PHP value.

## Notes

-   The API is similar to [PSR-16](https://www.php-fig.org/psr/psr-16/) (Simple Cache), but not a strict implementation.
-   Call `setDriver` once before the first cache use. The driver is shared by every cache instance, namespaced ones included.
