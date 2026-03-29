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

### Filesystem

Stores each cache entry as a file inside the given directory. The directory must exist and be writable.

```php
$cache = $mf('cache');
$cache::setDriver('filename', '/path/to/cache/dir');
```

Each entry is stored as `{key}.cache`. TTL is enforced on read.

### Database

Uses a PDO connection (MySQL or SQLite). The table `crystal_cache` is created automatically if it does not exist.

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

## Key Requirements

-   Keys must be non-empty strings matching `/^[A-Za-z0-9_.-]+$/`.
-   Values can be any serializable PHP value.

## Notes

-   The API is similar to [PSR-16](https://www.php-fig.org/psr/psr-16/) (Simple Cache), but not a strict implementation.
-   Call `setDriver` once before the first cache use. Changing driver mid-request clears the previous driver's state from the active instance.
