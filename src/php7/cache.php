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
 * @see https://www.php.net/manual/en/book.apcu.php
 */
$deps['cache'] = new class () {
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
        $success = false;
        $value = apcu_fetch($key, $success);

        return $success ? $value : $default;
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

        return apcu_store($key, $value, $ttl ?? 0);
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

        return apcu_delete($key);
    }

    /**
     * Clears the entire cache.
     *
     * @return bool Success
     */
    public function clear()
    {
        return apcu_clear_cache();
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

        return apcu_exists($key);
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
