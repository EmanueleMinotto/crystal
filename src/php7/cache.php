<?php

/**
 * Exposes a PSR-16-like cache object via $deps['cache'] supporting:
 * - get, set, delete, clear
 * - getMultiple, setMultiple, deleteMultiple
 * - has
 *
 * Keys must be non-empty strings matching /^[A-Za-z0-9_.-]+$/.
 * Values can be any serializable PHP value.
 *
 * Drivers: apcu (default), filename, database, custom.
 *
 * @see https://www.php.net/manual/en/book.apcu.php
 */
$deps['cache'] = new class () {
    /**
     * Active driver instance.
     *
     * @var object|null
     */
    private static $driver = null;

    /**
     * Configure the cache driver.
     *
     * Built-in drivers:
     *   setDriver('apcu')                  — APCu (default)
     *   setDriver('filename', '/path/dir') — filesystem
     *   setDriver('database', $pdo)        — PDO (MySQL/SQLite)
     *
     * Custom driver (must implement get/set/delete/has/clear):
     *   setDriver($customObject)
     *
     * @param  string|object             $driver Driver name or custom driver object
     * @param  mixed                     $config Driver-specific configuration
     * @throws \InvalidArgumentException If driver is unknown or custom object is invalid
     * @throws \RuntimeException         If driver cannot be initialised
     */
    public static function setDriver($driver, $config = null)
    {
        if (is_object($driver)) {
            $required = array('get', 'set', 'delete', 'has', 'clear');
            $missing = array();

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

            static::$driver = $driver;

            return;
        }

        switch ($driver) {
            case 'apcu':
                if (!function_exists('apcu_enabled') || !apcu_enabled()) {
                    throw new \RuntimeException('APCu is not available or not enabled');
                }

                static::$driver = new class () {
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
                };

                break;

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

                static::$driver = new class ($config) {
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
                        $data = serialize(array(
                            'value' => $value,
                            'expires' => ($ttl !== null && $ttl > 0) ? time() + $ttl : null,
                        ));

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

                    private function path($key)
                    {
                        return $this->dir.DIRECTORY_SEPARATOR.$key.'.cache';
                    }
                };

                break;

            case 'database':
                if (!($config instanceof \PDO)) {
                    throw new \InvalidArgumentException(
                        'database driver requires a PDO instance as second argument'
                    );
                }

                static::$driver = new class ($config) {
                    private $pdo;

                    public function __construct(\PDO $pdo)
                    {
                        $this->pdo = $pdo;
                        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                        $this->pdo->exec(
                            'CREATE TABLE IF NOT EXISTS crystal_cache ('
                            .'cache_key VARCHAR(255) NOT NULL PRIMARY KEY,'
                            .'cache_value LONGTEXT NOT NULL,'
                            .'expires_at INT DEFAULT NULL'
                            .')'
                        );
                    }

                    public function get($key, $default = null)
                    {
                        $stmt = $this->pdo->prepare(
                            'SELECT cache_value, expires_at FROM crystal_cache WHERE cache_key = ?'
                        );
                        $stmt->execute(array($key));
                        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

                        if (!$row) {
                            return $default;
                        }

                        if ($row['expires_at'] !== null && time() > (int) $row['expires_at']) {
                            $this->delete($key);

                            return $default;
                        }

                        return unserialize($row['cache_value']);
                    }

                    public function set($key, $value, $ttl = null)
                    {
                        $expires = ($ttl !== null && $ttl > 0) ? time() + $ttl : null;
                        $stmt = $this->pdo->prepare(
                            'REPLACE INTO crystal_cache (cache_key, cache_value, expires_at) VALUES (?, ?, ?)'
                        );

                        return $stmt->execute(array($key, serialize($value), $expires));
                    }

                    public function delete($key)
                    {
                        $stmt = $this->pdo->prepare(
                            'DELETE FROM crystal_cache WHERE cache_key = ?'
                        );

                        return $stmt->execute(array($key));
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
                        $stmt->execute(array($key));
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
                };

                break;

            default:
                throw new \InvalidArgumentException("Unknown cache driver: $driver");
        }
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

        return static::resolveDriver()->get($key, $default);
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

        return static::resolveDriver()->set($key, $value, $ttl);
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

        return static::resolveDriver()->delete($key);
    }

    /**
     * Clears the entire cache.
     *
     * @return bool Success
     */
    public function clear()
    {
        return static::resolveDriver()->clear();
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
        $result = array();

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

        return static::resolveDriver()->has($key);
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
