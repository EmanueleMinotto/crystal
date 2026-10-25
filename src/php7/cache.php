<?php

/**
 * Exposes a PSR-16-like cache object via $deps['cache'] supporting:
 * - get, set, delete, clear
 * - getMultiple, setMultiple, deleteMultiple
 * - has
 * - withNamespace, to isolate keys in (nested) namespaces
 *
 * Keys must be non-empty strings matching /^[A-Za-z0-9_.-]+$/.
 * Values can be any serializable PHP value.
 *
 * Drivers: apcu (default), array, filename, database, memcached, redis,
 * chain, custom.
 *
 * @see https://www.php.net/manual/en/book.apcu.php
 * @see https://www.php.net/manual/en/book.memcached.php
 * @see https://github.com/phpredis/phpredis
 */
$deps['cache'] = new class () {
    /**
     * Active driver instance.
     *
     * @var object|null
     */
    private static $driver = null;

    /**
     * Prefix of the keys of this instance, empty for the root namespace.
     * Each namespace is followed by ":", a character not allowed in keys, so
     * namespaced keys can't collide with the keys of other namespaces.
     *
     * @var string
     */
    private $namespace = '';

    /**
     * Configure the cache driver.
     *
     * Built-in drivers:
     *   setDriver('apcu')                  — APCu (default)
     *   setDriver('array')                 — in memory, for the current request only
     *   setDriver('filename', '/path/dir') — filesystem
     *   setDriver('database', $pdo)        — PDO (MySQL/SQLite)
     *   setDriver('memcached', $memcached) — Memcached instance (memcached extension)
     *   setDriver('redis', $redis)         — Redis instance (redis extension)
     *   setDriver('chain', [...])          — list of drivers, from the fastest to the slowest
     *
     * Custom driver (must implement get/set/delete/has/clear, clearPrefix is
     * optional and required only to clear a namespace):
     *   setDriver($customObject)
     *
     * @param  string|object             $driver Driver name or custom driver object
     * @param  mixed                     $config Driver-specific configuration
     * @throws \InvalidArgumentException If driver is unknown or custom object is invalid
     * @throws \RuntimeException         If driver cannot be initialised
     */
    public static function setDriver($driver, $config = null)
    {
        static::$driver = static::createDriver($driver, $config);
    }

    /**
     * Creates a driver instance, used by setDriver and by the chain driver.
     *
     * @param  string|object             $driver Driver name or custom driver object
     * @param  mixed                     $config Driver-specific configuration
     * @return object
     * @throws \InvalidArgumentException If driver is unknown or custom object is invalid
     * @throws \RuntimeException         If driver cannot be initialised
     */
    private static function createDriver($driver, $config = null)
    {
        if (is_object($driver)) {
            $required = ['get', 'set', 'delete', 'has', 'clear'];
            $missing = [];

            foreach ($required as $method) {
                if (!method_exists($driver, $method)) {
                    $missing[] = $method;
                }
            }

            if (!empty($missing)) {
                throw new \InvalidArgumentException(
                    'Custom driver is missing methods: '.implode(', ', $missing)
                );
            }

            return $driver;
        }

        switch ($driver) {
            case 'apcu':
                if (!function_exists('apcu_enabled') || !apcu_enabled()) {
                    throw new \RuntimeException('APCu is not available or not enabled');
                }

                return new class () {
                    public function get($key, $default = null)
                    {
                        $success = false;
                        $value = apcu_fetch($key, $success);

                        return $success ? $value : $default;
                    }

                    public function set($key, $value, $ttl = null)
                    {
                        return apcu_store($key, $value, $ttl ?? 0);
                    }

                    public function delete($key)
                    {
                        return apcu_delete($key);
                    }

                    public function clear()
                    {
                        return apcu_clear_cache();
                    }

                    public function has($key)
                    {
                        return apcu_exists($key);
                    }

                    public function clearPrefix($prefix)
                    {
                        apcu_delete(new \APCUIterator('/^'.preg_quote($prefix, '/').'/', APC_ITER_KEY));

                        return true;
                    }
                };

            case 'array':
                return new class () {
                    /**
                     * Serialized values and expiration timestamps, by key.
                     *
                     * @var array
                     */
                    private $items = [];

                    public function get($key, $default = null)
                    {
                        if (!$this->has($key)) {
                            return $default;
                        }

                        // values are serialized to behave like the other drivers:
                        // objects changed after set() don't change the cached value
                        return unserialize($this->items[$key]['value']);
                    }

                    public function set($key, $value, $ttl = null)
                    {
                        $this->items[$key] = [
                            'value' => serialize($value),
                            'expires' => ($ttl !== null && $ttl > 0) ? time() + $ttl : null,
                        ];

                        return true;
                    }

                    public function delete($key)
                    {
                        unset($this->items[$key]);

                        return true;
                    }

                    public function clear()
                    {
                        $this->items = [];

                        return true;
                    }

                    public function has($key)
                    {
                        if (!isset($this->items[$key])) {
                            return false;
                        }

                        if ($this->items[$key]['expires'] !== null && time() > $this->items[$key]['expires']) {
                            unset($this->items[$key]);

                            return false;
                        }

                        return true;
                    }

                    public function clearPrefix($prefix)
                    {
                        foreach (array_keys($this->items) as $key) {
                            if (strpos($key, $prefix) === 0) {
                                unset($this->items[$key]);
                            }
                        }

                        return true;
                    }
                };

            case 'filename':
                if (!is_string($config) || $config === '') {
                    throw new \InvalidArgumentException(
                        'filename driver requires a directory path as second argument'
                    );
                }

                if (!is_dir($config) || !is_writable($config)) {
                    throw new \RuntimeException(
                        "Cache directory is not writable: $config"
                    );
                }

                return new class ($config) {
                    private $dir;

                    public function __construct($dir)
                    {
                        $this->dir = rtrim($dir, DIRECTORY_SEPARATOR);
                    }

                    public function get($key, $default = null)
                    {
                        $file = $this->path($key);

                        if (!file_exists($file)) {
                            return $default;
                        }

                        $data = unserialize(file_get_contents($file));

                        if ($data['expires'] !== null && time() > $data['expires']) {
                            unlink($file);

                            return $default;
                        }

                        return $data['value'];
                    }

                    public function set($key, $value, $ttl = null)
                    {
                        $data = serialize([
                            'value' => $value,
                            'expires' => ($ttl !== null && $ttl > 0) ? time() + $ttl : null,
                        ]);

                        return file_put_contents($this->path($key), $data) !== false;
                    }

                    public function delete($key)
                    {
                        $file = $this->path($key);

                        return !file_exists($file) || unlink($file);
                    }

                    public function clear()
                    {
                        foreach (glob($this->dir.DIRECTORY_SEPARATOR.'*.cache') as $file) {
                            unlink($file);
                        }

                        return true;
                    }

                    public function has($key)
                    {
                        $file = $this->path($key);

                        if (!file_exists($file)) {
                            return false;
                        }

                        $data = unserialize(file_get_contents($file));

                        if ($data['expires'] !== null && time() > $data['expires']) {
                            unlink($file);

                            return false;
                        }

                        return true;
                    }

                    public function clearPrefix($prefix)
                    {
                        foreach (glob($this->dir.DIRECTORY_SEPARATOR.rawurlencode($prefix).'*.cache') as $file) {
                            unlink($file);
                        }

                        return true;
                    }

                    private function path($key)
                    {
                        // the namespace separator ":" is not allowed in Windows file names
                        return $this->dir.DIRECTORY_SEPARATOR.rawurlencode($key).'.cache';
                    }
                };

            case 'database':
                if (!($config instanceof \PDO)) {
                    throw new \InvalidArgumentException(
                        'database driver requires a PDO instance as second argument'
                    );
                }

                return new class ($config) {
                    private $pdo;
                    private $driver;

                    public function __construct(\PDO $pdo)
                    {
                        $this->pdo = $pdo;
                        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                        $this->driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
                        $this->pdo->exec(
                            'CREATE TABLE IF NOT EXISTS crystal_cache ('
                            .'cache_key VARCHAR(255) NOT NULL PRIMARY KEY,'
                            // TEXT is limited to 64KB in MySQL
                            .'cache_value '.('mysql' === $this->driver ? 'LONGTEXT' : 'TEXT').' NOT NULL,'
                            .'expires_at INT DEFAULT NULL'
                            .')'
                        );
                    }

                    public function get($key, $default = null)
                    {
                        $stmt = $this->pdo->prepare(
                            'SELECT cache_value, expires_at FROM crystal_cache WHERE cache_key = ?'
                        );
                        $stmt->execute([$key]);
                        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

                        if (!$row) {
                            return $default;
                        }

                        if ($row['expires_at'] !== null && time() > (int) $row['expires_at']) {
                            $this->delete($key);

                            return $default;
                        }

                        return unserialize($this->decode($row['cache_value']));
                    }

                    public function set($key, $value, $ttl = null)
                    {
                        $expires = ($ttl !== null && $ttl > 0) ? time() + $ttl : null;
                        $params = [$key, $this->encode(serialize($value)), $expires];

                        switch ($this->driver) {
                            case 'mysql':
                            case 'sqlite':
                                return $this->pdo
                                    ->prepare('REPLACE INTO crystal_cache (cache_key, cache_value, expires_at) VALUES (?, ?, ?)')
                                    ->execute($params);

                            case 'pgsql':
                                return $this->pdo
                                    ->prepare(
                                        'INSERT INTO crystal_cache (cache_key, cache_value, expires_at) VALUES (?, ?, ?) '
                                        .'ON CONFLICT (cache_key) DO UPDATE SET cache_value = EXCLUDED.cache_value, expires_at = EXCLUDED.expires_at'
                                    )
                                    ->execute($params);
                        }

                        // other databases: delete and insert in a transaction
                        $transaction = !$this->pdo->inTransaction() && $this->pdo->beginTransaction();

                        try {
                            $this->delete($key);
                            $this->pdo
                                ->prepare('INSERT INTO crystal_cache (cache_key, cache_value, expires_at) VALUES (?, ?, ?)')
                                ->execute($params);
                        } catch (\Exception $exception) {
                            if ($transaction) {
                                $this->pdo->rollBack();
                            }

                            throw $exception;
                        }

                        return !$transaction || $this->pdo->commit();
                    }

                    /**
                     * PostgreSQL TEXT columns can't contain NUL bytes, used by
                     * serialize() for private and protected properties.
                     */
                    private function encode($value)
                    {
                        return 'pgsql' === $this->driver ? base64_encode($value) : $value;
                    }

                    private function decode($value)
                    {
                        return 'pgsql' === $this->driver ? base64_decode($value) : $value;
                    }

                    public function delete($key)
                    {
                        $stmt = $this->pdo->prepare(
                            'DELETE FROM crystal_cache WHERE cache_key = ?'
                        );

                        return $stmt->execute([$key]);
                    }

                    public function clear()
                    {
                        return $this->pdo->exec('DELETE FROM crystal_cache') !== false;
                    }

                    public function has($key)
                    {
                        $stmt = $this->pdo->prepare(
                            'SELECT expires_at FROM crystal_cache WHERE cache_key = ?'
                        );
                        $stmt->execute([$key]);
                        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

                        if (!$row) {
                            return false;
                        }

                        if ($row['expires_at'] !== null && time() > (int) $row['expires_at']) {
                            $this->delete($key);

                            return false;
                        }

                        return true;
                    }

                    public function clearPrefix($prefix)
                    {
                        // SUBSTR instead of LIKE, "_" is a valid key character and a LIKE wildcard
                        $stmt = $this->pdo->prepare(
                            'DELETE FROM crystal_cache WHERE SUBSTR(cache_key, 1, ?) = ?'
                        );

                        return $stmt->execute([strlen($prefix), $prefix]);
                    }
                };

            case 'memcached':
                if (!extension_loaded('memcached')) {
                    throw new \RuntimeException('The memcached extension is not available');
                }

                if (!($config instanceof \Memcached)) {
                    throw new \InvalidArgumentException(
                        'memcached driver requires a Memcached instance as second argument'
                    );
                }

                return new class ($config) {
                    private $memcached;

                    public function __construct(\Memcached $memcached)
                    {
                        $this->memcached = $memcached;
                    }

                    public function get($key, $default = null)
                    {
                        $value = $this->memcached->get($this->key($key));

                        // false is both a valid value and the failure result
                        return $this->memcached->getResultCode() === \Memcached::RES_SUCCESS ? $value : $default;
                    }

                    public function set($key, $value, $ttl = null)
                    {
                        $expiration = 0;

                        if ($ttl !== null && $ttl > 0) {
                            // memcached reads expirations longer than 30 days as Unix timestamps
                            $expiration = $ttl > 2592000 ? time() + $ttl : $ttl;
                        }

                        return $this->memcached->set($this->key($key), $value, $expiration);
                    }

                    public function delete($key)
                    {
                        return $this->memcached->delete($this->key($key))
                            || $this->memcached->getResultCode() === \Memcached::RES_NOTFOUND;
                    }

                    public function clear()
                    {
                        return $this->memcached->flush();
                    }

                    public function has($key)
                    {
                        $this->memcached->get($this->key($key));

                        return $this->memcached->getResultCode() === \Memcached::RES_SUCCESS;
                    }

                    /**
                     * Memcached can't list its keys, so namespaces are versioned:
                     * a new version makes the keys stored with the previous one
                     * unreachable, they're removed by memcached when memory is needed.
                     */
                    public function clearPrefix($prefix)
                    {
                        $namespaces = explode(':', rtrim($prefix, ':'));
                        $last = array_pop($namespaces);

                        return $this->memcached->set(
                            $this->versioned($namespaces).$last.':',
                            bin2hex(random_bytes(8))
                        );
                    }

                    /**
                     * Converts "a:b:key" to "a:{version of a}:b:{version of b}:key".
                     */
                    private function key($key)
                    {
                        $namespaces = explode(':', $key);
                        $name = array_pop($namespaces);

                        return $this->versioned($namespaces).$name;
                    }

                    private function versioned(array $namespaces)
                    {
                        $prefix = '';

                        foreach ($namespaces as $namespace) {
                            // ends with ":", so it can't be a key of the namespace
                            $versionKey = $prefix.$namespace.':';
                            $version = $this->memcached->get($versionKey);

                            if ($this->memcached->getResultCode() !== \Memcached::RES_SUCCESS) {
                                $version = bin2hex(random_bytes(8));

                                // add() fails when a concurrent request stored a version first
                                if (!$this->memcached->add($versionKey, $version)) {
                                    $version = $this->memcached->get($versionKey);
                                }
                            }

                            $prefix = $versionKey.$version.':';
                        }

                        return $prefix;
                    }
                };

            case 'redis':
                if (!extension_loaded('redis')) {
                    throw new \RuntimeException('The redis extension is not available');
                }

                if (!($config instanceof \Redis)) {
                    throw new \InvalidArgumentException(
                        'redis driver requires a Redis instance as second argument'
                    );
                }

                return new class ($config) {
                    private $redis;

                    public function __construct(\Redis $redis)
                    {
                        $this->redis = $redis;
                    }

                    public function get($key, $default = null)
                    {
                        $value = $this->redis->get($key);

                        // values are always serialized strings, false means not found
                        return $value === false ? $default : unserialize($value);
                    }

                    public function set($key, $value, $ttl = null)
                    {
                        if ($ttl !== null && $ttl > 0) {
                            return $this->redis->setex($key, $ttl, serialize($value));
                        }

                        return $this->redis->set($key, serialize($value));
                    }

                    public function delete($key)
                    {
                        $this->redis->del($key);

                        return true;
                    }

                    public function clear()
                    {
                        return $this->redis->flushDB();
                    }

                    public function has($key)
                    {
                        // integer since phpredis 4.0, boolean before
                        return (bool) $this->redis->exists($key);
                    }

                    public function clearPrefix($prefix)
                    {
                        // keys and namespaces can't contain glob special characters
                        $iterator = null;

                        do {
                            $keys = $this->redis->scan($iterator, $prefix.'*', 1000);

                            if (!empty($keys)) {
                                $this->redis->del($keys);
                            }
                        } while ($iterator > 0);

                        return true;
                    }
                };

            case 'chain':
                if (!is_array($config) || empty($config)) {
                    throw new \InvalidArgumentException(
                        'chain driver requires a non-empty array of drivers as second argument'
                    );
                }

                $drivers = [];

                // each driver is a name, an object or a [name, config] pair
                foreach ($config as $item) {
                    $drivers[] = is_array($item)
                        ? static::createDriver($item[0] ?? null, $item[1] ?? null)
                        : static::createDriver($item);
                }

                return new class ($drivers) {
                    private $drivers;

                    public function __construct(array $drivers)
                    {
                        $this->drivers = $drivers;
                    }

                    /**
                     * Returns the value from the first driver that has it and
                     * stores it in the previous (faster) drivers.
                     */
                    public function get($key, $default = null)
                    {
                        foreach ($this->drivers as $index => $driver) {
                            $entry = $driver->get($key);

                            if (!is_array($entry) || !array_key_exists('value', $entry) || !array_key_exists('expires', $entry)) {
                                continue;
                            }

                            $ttl = null;

                            if ($entry['expires'] !== null) {
                                $ttl = $entry['expires'] - time();

                                if ($ttl <= 0) {
                                    continue;
                                }
                            }

                            for ($i = 0; $i < $index; $i++) {
                                $this->drivers[$i]->set($key, $entry, $ttl);
                            }

                            return $entry['value'];
                        }

                        return $default;
                    }

                    /**
                     * Values are stored with their expiration timestamp, so the
                     * remaining time-to-live is known when copied by get().
                     */
                    public function set($key, $value, $ttl = null)
                    {
                        $entry = [
                            'value' => $value,
                            'expires' => ($ttl !== null && $ttl > 0) ? time() + $ttl : null,
                        ];
                        $success = true;

                        foreach ($this->drivers as $driver) {
                            $success = $driver->set($key, $entry, $ttl) && $success;
                        }

                        return $success;
                    }

                    public function delete($key)
                    {
                        $success = true;

                        foreach ($this->drivers as $driver) {
                            $success = $driver->delete($key) && $success;
                        }

                        return $success;
                    }

                    public function clear()
                    {
                        $success = true;

                        foreach ($this->drivers as $driver) {
                            $success = $driver->clear() && $success;
                        }

                        return $success;
                    }

                    public function has($key)
                    {
                        foreach ($this->drivers as $driver) {
                            if ($driver->has($key)) {
                                return true;
                            }
                        }

                        return false;
                    }

                    public function clearPrefix($prefix)
                    {
                        // checked before clearing, to not leave the drivers out of sync
                        foreach ($this->drivers as $driver) {
                            if (!method_exists($driver, 'clearPrefix')) {
                                throw new \RuntimeException('The cache driver does not support clearing a namespace');
                            }
                        }

                        $success = true;

                        foreach ($this->drivers as $driver) {
                            $success = $driver->clearPrefix($prefix) && $success;
                        }

                        return $success;
                    }
                };

            default:
                throw new \InvalidArgumentException("Unknown cache driver: $driver");
        }
    }

    /**
     * Returns a cache instance whose keys are isolated in a sub namespace of
     * the current one, sharing the same driver.
     *
     *   $users = $cache->withNamespace('users');
     *   $admins = $users->withNamespace('admins'); // keys prefixed by "users:admins:"
     *
     * @param  string                    $namespace Namespace name, same rules as keys
     * @return object                    Namespaced cache instance
     * @throws \InvalidArgumentException If namespace is invalid
     */
    public function withNamespace($namespace)
    {
        if (!self::isValidKey($namespace)) {
            throw new \InvalidArgumentException("Invalid namespace: $namespace");
        }

        $cache = clone $this;
        $cache->namespace .= $namespace.':';

        return $cache;
    }

    /**
     * Fetches a value from the cache by key.
     *
     * @param  string                    $key     Cache key
     * @param  mixed                     $default Value to return if key is not found
     * @return mixed                     Cached value or $default
     * @throws \InvalidArgumentException If key is invalid
     */
    public function get($key, $default = null)
    {
        if (!self::isValidKey($key)) {
            throw new \InvalidArgumentException("Invalid key: $key");
        }

        return static::resolveDriver()->get($this->namespace.$key, $default);
    }

    /**
     * Stores a value in the cache under the given key.
     *
     * @param  string                    $key   Cache key
     * @param  mixed                     $value Value to store
     * @param  int|null                  $ttl   Time-to-live in seconds (optional)
     * @return bool                      Success
     * @throws \InvalidArgumentException If key is invalid
     */
    public function set($key, $value, $ttl = null)
    {
        if (!self::isValidKey($key)) {
            throw new \InvalidArgumentException("Invalid key: $key");
        }

        return static::resolveDriver()->set($this->namespace.$key, $value, $ttl);
    }

    /**
     * Deletes a value from the cache by key.
     *
     * @param  string                    $key Cache key
     * @return bool                      Success
     * @throws \InvalidArgumentException If key is invalid
     */
    public function delete($key)
    {
        if (!self::isValidKey($key)) {
            throw new \InvalidArgumentException("Invalid key: $key");
        }

        return static::resolveDriver()->delete($this->namespace.$key);
    }

    /**
     * Clears the entire cache or, for a namespaced instance, only the keys of
     * the namespace and of its sub namespaces.
     *
     * @return bool              Success
     * @throws \RuntimeException If the driver can't clear a namespace
     */
    public function clear()
    {
        $driver = static::resolveDriver();

        if ($this->namespace === '') {
            return $driver->clear();
        }

        if (!method_exists($driver, 'clearPrefix')) {
            throw new \RuntimeException('The cache driver does not support clearing a namespace');
        }

        return $driver->clearPrefix($this->namespace);
    }

    /**
     * Fetches multiple values from the cache.
     *
     * @param  iterable                  $keys    List of cache keys
     * @param  mixed                     $default Value to return for missing keys
     * @return array                     Associative array of key => value
     * @throws \InvalidArgumentException If keys is not iterable
     */
    public function getMultiple($keys, $default = null)
    {
        if (!is_iterable($keys)) {
            throw new \InvalidArgumentException('Keys must be iterable');
        }
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    /**
     * Stores multiple key-value pairs in the cache.
     *
     * @param  iterable                  $values Associative array of key => value
     * @param  int|null                  $ttl    Time-to-live in seconds (optional)
     * @return bool                      Success
     * @throws \InvalidArgumentException If values is not iterable
     */
    public function setMultiple($values, $ttl = null)
    {
        if (!is_iterable($values)) {
            throw new \InvalidArgumentException('Values must be iterable');
        }
        $success = true;

        foreach ($values as $key => $value) {
            $success = $success && $this->set($key, $value, $ttl);
        }

        return $success;
    }

    /**
     * Deletes multiple values from the cache.
     *
     * @param  iterable                  $keys List of cache keys
     * @return bool                      Success
     * @throws \InvalidArgumentException If keys is not iterable
     */
    public function deleteMultiple($keys)
    {
        if (!is_iterable($keys)) {
            throw new \InvalidArgumentException('Keys must be iterable');
        }
        $success = true;

        foreach ($keys as $key) {
            $success = $success && $this->delete($key);
        }

        return $success;
    }

    /**
     * Checks if a cache key exists.
     *
     * @param  string                    $key Cache key
     * @return bool                      True if key exists
     * @throws \InvalidArgumentException If key is invalid
     */
    public function has($key)
    {
        if (!self::isValidKey($key)) {
            throw new \InvalidArgumentException("Invalid key: $key");
        }

        return static::resolveDriver()->has($this->namespace.$key);
    }

    /**
     * Returns the active driver, lazily initialising APCu if none has been set.
     *
     * @return object
     */
    private static function resolveDriver()
    {
        if (static::$driver === null) {
            static::setDriver('apcu');
        }

        return static::$driver;
    }

    /**
     * Validates a cache key.
     *
     * @param  string $key Cache key
     * @return bool   True if valid
     */
    private static function isValidKey($key)
    {
        return is_string($key) && $key !== '' && preg_match('/^[A-Za-z0-9_.-]+$/', $key);
    }
};
