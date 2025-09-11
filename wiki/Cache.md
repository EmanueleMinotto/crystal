The cache is provided as a [PSR-16](https://www.php-fig.org/psr/psr-16/)-like object.

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

-   The cache uses [APCu](https://www.php.net/manual/en/book.apcu.php) for storage. Make sure APCu is enabled in your PHP environment.
-   The API is similar to [PSR-16](https://www.php-fig.org/psr/psr-16/) (Simple Cache), but not a strict implementation.
