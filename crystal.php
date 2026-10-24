<?php

/**
 * Autoloading based on the PSR-0 standard and extended predefined configuration
 * https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md
 */
spl_autoload_register(function ($class) {
    // Use predefined paths
    $paths = explode(PATH_SEPARATOR, get_include_path());

    // remove duplicated paths
    $paths = array_values(array_unique($paths));

    // Realpaths and URLs
    array_walk($paths, function (&$path) {
        $path = rtrim(realpath($path), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
    });
    array_filter($paths, function ($path) {
        return !is_null(trim($path, '/'.DIRECTORY_SEPARATOR));
    });

    // Use predefined extensions
    $extensions = explode(',', spl_autoload_extensions());

    // Remove initial backslash `\`
    $class = ltrim($class, chr(92));
    // Explode by `\`
    $tokens = explode(chr(92), $class);
    // Each `_` character in the CLASS NAME is converted to a `DIRECTORY_SEPARATOR`.
    // The `_` character has no special meaning in the namespace.
    $class_tokens = explode('_', end($tokens));
    array_pop($tokens);

    array_walk($class_tokens, function (&$class_token) use (&$tokens) {
        $tokens[] = $class_token;
    });

    // Check if file exists and require(_once) it
    foreach ($paths as $path) {
        foreach ($extensions as $extension) {
            $uri = $path.implode(DIRECTORY_SEPARATOR, $tokens).$extension;

            if (file_exists($uri)) {
                // spl_autoload_register returns bool
                // http://it2.php.net/manual/en/function.spl-autoload-register.php
                return (require_once $uri) === 1;
            }
        }
    }

    // spl_autoload_register returns bool
    // http://it2.php.net/manual/en/function.spl-autoload-register.php
    return false;
});

/**
 * Polyfill for array_is_list() function (PHP < 8.1).
 *
 * Checks if an array's keys are consecutive numbers starting from 0.
 * Returns true for empty arrays and arrays with sequential integer keys.
 *
 * @see https://www.php.net/manual/en/function.array-is-list.php
 */
if (!function_exists('array_is_list')) {
    /**
     * Determines if the given array is a list (sequential integer keys starting from 0).
     *
     * @param  array $array Array to check
     * @return bool  True if array is a list, false otherwise
     */
    function array_is_list($array)
    {
        if ([] === $array || $array === array_values($array)) {
            return true;
        }

        $nextKey = -1;

        foreach ($array as $k => $v) {
            if ($k !== ++$nextKey) {
                return false;
            }
        }

        return true;
    }
}

/**
 * Polyfill for is_iterable() function (PHP < 7.1).
 *
 * Provides the is_iterable() function if it does not exist, allowing code to check
 * if a variable is iterable (array or Traversable object).
 *
 * @see https://www.php.net/manual/en/function.is-iterable.php
 */

if (!function_exists('is_iterable')) {
    /**
     * Checks if the given variable is iterable (array or Traversable).
     *
     * @param  mixed $obj Variable to check
     * @return bool  True if iterable, false otherwise
     */
    function is_iterable($obj)
    {
        return is_array($obj) || (is_object($obj) && ($obj instanceof \Traversable));
    }
}

/**
 * A PHP (7.0+) microframework based on anonymous functions and classes.
 */
return new class () {
    /**
     * Pattern returned by `router:method-not-allowed`, resolved when the request is dispatched.
     */
    public const METHOD_NOT_ALLOWED = "\0router:method-not-allowed";

    /**
     * Methods checked when an URL is matched with a different request method.
     */
    public const METHODS = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    /**
     * Used to store functions and allow recursive callbacks in CLI mode.
     *
     * @var null|callable
     */
    private $deploy;

    /**
     * Defined matches.
     *
     * @var array
     */
    private $matches = [];

    /**
     * Dependency Injection callbacks, used for settings too.
     *
     * @var array
     */
    private $deps = [];

    /**
     * Whether the script is running in command line mode, detected once.
     *
     * @var null|bool
     */
    private $cli;

    /**
     * Defined routes, dispatched when the script ends.
     *
     * @var array
     */
    private $routes = [];

    /**
     * Patterns returned by `router:not-found`.
     *
     * @var array
     */
    private $notFound = [];

    /**
     * Middlewares wrapping every request.
     *
     * @var callable[]
     */
    private $middlewares = [];

    /**
     * Stack of the groups currently defined.
     *
     * @var array
     */
    private $groups = [];

    /**
     * When enabled routes are dispatched only using `handle()`.
     *
     * @var bool
     */
    private $testing = false;

    /**
     * Whether the shutdown function dispatching routes is registered.
     *
     * @var bool
     */
    private $registered = false;

    public function __construct()
    {
        // services write on $deps, so it has to be bound to the property
        $deps = &$this->deps;

        /**
         * Exposes authentication helpers via $deps['auth']:
         * - JSON Web Tokens encoding and decoding (HS256, HS384, HS512)
         * - Bearer token and API key middlewares
         *
         * The authenticated identity is stored in the `user` request attribute.
         *
         * @see https://www.rfc-editor.org/rfc/rfc7519 (JWT)
         * @see https://www.rfc-editor.org/rfc/rfc6750 (Bearer Token Usage)
         */
        $deps['auth'] = new class ($this) {
            /**
             * Framework instance, used to resolve other services.
             *
             * @var callable
             */
            private $mf;

            /**
             * Supported algorithms.
             *
             * @var array
             */
            private $algorithms = [
                'HS256' => 'sha256',
                'HS384' => 'sha384',
                'HS512' => 'sha512',
            ];

            public function __construct(callable $mf)
            {
                $this->mf = $mf;
            }

            /**
             * Encode a JWT.
             *
             * @param array  $claims    Payload, `iat` is added if missing
             * @param string $secret    Shared secret
             * @param int    $ttl       Seconds before expiration, 0 to skip the `exp` claim
             * @param string $algorithm One of HS256, HS384, HS512
             *
             * @return string
             */
            public function encode(array $claims, string $secret, int $ttl = 3600, string $algorithm = 'HS256')
            {
                if (!isset($this->algorithms[$algorithm])) {
                    throw new InvalidArgumentException(sprintf('Unsupported algorithm "%s"', $algorithm));
                }

                $claims += ['iat' => time()];

                if ($ttl > 0) {
                    $claims += ['exp' => $claims['iat'] + $ttl];
                }

                $segments = [
                    $this->base64(json_encode(['typ' => 'JWT', 'alg' => $algorithm])),
                    $this->base64(json_encode($claims)),
                ];
                $segments[] = $this->base64(hash_hmac($this->algorithms[$algorithm], implode('.', $segments), $secret, true));

                return implode('.', $segments);
            }

            /**
             * Decode and verify a JWT.
             *
             * @param string   $token      Encoded token
             * @param string   $secret     Shared secret
             * @param string[] $algorithms Accepted algorithms
             * @param int      $leeway     Seconds of tolerance for time based claims
             *
             * @throws UnexpectedValueException If the token is invalid or expired
             *
             * @return array Claims
             */
            public function decode(string $token, string $secret, array $algorithms = ['HS256'], int $leeway = 0)
            {
                $segments = explode('.', $token);

                if (3 !== count($segments)) {
                    throw new UnexpectedValueException('Wrong number of segments');
                }

                $header = json_decode($this->unbase64($segments[0]), true);
                $claims = json_decode($this->unbase64($segments[1]), true);

                if (!is_array($header) || !is_array($claims)) {
                    throw new UnexpectedValueException('Malformed token');
                }

                $algorithm = $header['alg'] ?? null;

                if (!in_array($algorithm, $algorithms, true) || !isset($this->algorithms[$algorithm])) {
                    throw new UnexpectedValueException('Algorithm not allowed');
                }

                $signature = hash_hmac($this->algorithms[$algorithm], $segments[0].'.'.$segments[1], $secret, true);

                if (!hash_equals($signature, $this->unbase64($segments[2]))) {
                    throw new UnexpectedValueException('Signature verification failed');
                }

                $now = time();

                if (isset($claims['nbf']) && $claims['nbf'] > $now + $leeway) {
                    throw new UnexpectedValueException('Token not valid yet');
                }

                if (isset($claims['exp']) && $claims['exp'] <= $now - $leeway) {
                    throw new UnexpectedValueException('Token expired');
                }

                return $claims;
            }

            /**
             * Middleware requiring a Bearer token.
             *
             * @param string|Closure $verifier JWT secret, or `function ($token, $request)`
             *                                 returning the user or null
             *
             * @return callable
             */
            public function bearer($verifier, array $algorithms = ['HS256'])
            {
                return function ($request, callable $next) use ($verifier, $algorithms) {
                    $token = $request->bearerToken();

                    if (is_null($token)) {
                        throw $this->unauthorized('Bearer', 'Missing bearer token.');
                    }

                    if (is_object($verifier) && is_callable($verifier)) {
                        $user = call_user_func($verifier, $token, $request);
                    } else {
                        try {
                            $user = $this->decode($token, $verifier, $algorithms);
                        } catch (UnexpectedValueException $exception) {
                            throw $this->unauthorized('Bearer error="invalid_token"', $exception->getMessage().'.');
                        }
                    }

                    if (is_null($user) || false === $user) {
                        throw $this->unauthorized('Bearer error="invalid_token"', 'Invalid bearer token.');
                    }

                    $request->setAttribute('user', $user);

                    return $next($request);
                };
            }

            /**
             * Middleware requiring an API key.
             *
             * @param string[]|Closure $keys   Valid keys indexed by user (or a list),
             *                                 or `function ($key, $request)` returning the user or null
             * @param string           $header Header containing the key
             *
             * @return callable
             */
            public function apiKey($keys, string $header = 'X-API-Key')
            {
                return function ($request, callable $next) use ($keys, $header) {
                    $key = $request->header($header);
                    $user = null;

                    if (!is_null($key) && is_object($keys) && is_callable($keys)) {
                        $user = call_user_func($keys, $key, $request);
                    } elseif (!is_null($key)) {
                        foreach ((array) $keys as $name => $valid) {
                            if (hash_equals((string) $valid, $key)) {
                                $user = is_int($name) ? $key : $name;

                                break;
                            }
                        }
                    }

                    if (is_null($user) || false === $user) {
                        throw $this->unauthorized('ApiKey header="'.$header.'"', is_null($key) ? 'Missing API key.' : 'Invalid API key.');
                    }

                    $request->setAttribute('user', $user);

                    return $next($request);
                };
            }

            private function unauthorized(string $challenge, string $detail)
            {
                return call_user_func($this->mf, 'errors')->create(401, $detail, [], ['WWW-Authenticate' => $challenge]);
            }

            private function base64(string $data)
            {
                return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
            }

            private function unbase64(string $data)
            {
                return (string) base64_decode(strtr($data, '-_', '+/'), true);
            }
        };

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
                                $stmt->execute([$key]);
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

                                return $stmt->execute([$key, serialize($value), $expires]);
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

        /**
         * Exposes a configuration object via $deps['config'] supporting:
         * - load, get, set, has, all (keys use the dot notation, e.g. "database.host")
         * - loadEnv, env (environment variables and .env files)
         *
         * Configuration sources: arrays, .php (returning an array), .json and .ini files.
         *
         * @see https://www.php.net/manual/en/function.getenv.php
         * @see https://www.php.net/manual/en/function.parse-ini-file.php
         */
        $deps['config'] = new class () {
            /**
             * Loaded configuration values.
             *
             * @var array
             */
            private $values = [];

            /**
             * Load configuration values, merged recursively into the existing ones.
             *
             * Associative arrays are merged key by key, lists and scalars are replaced.
             *
             * @param  array|string              $source Configuration array or file path (.php, .json, .ini)
             * @throws \InvalidArgumentException If the file type is unsupported or doesn't contain an array
             * @throws \RuntimeException         If the file is not readable or can't be parsed
             */
            public function load($source)
            {
                if (is_string($source)) {
                    $source = $this->read($source);
                }

                if (!is_array($source)) {
                    throw new \InvalidArgumentException('Configuration source must be an array or a file path');
                }

                $this->values = $this->merge($this->values, $source);
            }

            /**
             * Retrieve a configuration value.
             *
             * @param  string $key     Dot notation key (e.g. "database.host")
             * @param  mixed  $default Value returned when the key doesn't exist
             * @return mixed
             */
            public function get($key, $default = null)
            {
                $value = $this->values;

                foreach (explode('.', $key) as $segment) {
                    if (!is_array($value) || !array_key_exists($segment, $value)) {
                        return $default;
                    }

                    $value = $value[$segment];
                }

                return $value;
            }

            /**
             * Store a configuration value, intermediate levels are created when missing.
             *
             * @param string $key   Dot notation key (e.g. "database.host")
             * @param mixed  $value Value to store
             */
            public function set($key, $value)
            {
                $target = &$this->values;

                foreach (explode('.', $key) as $segment) {
                    if (!isset($target[$segment]) || !is_array($target[$segment])) {
                        $target[$segment] = [];
                    }

                    $target = &$target[$segment];
                }

                $target = $value;
            }

            /**
             * Check if a configuration value exists (null values included).
             *
             * @param  string $key Dot notation key (e.g. "database.host")
             * @return bool
             */
            public function has($key)
            {
                // a new object is identical only to itself, so it is a safe "missing" marker
                $missing = new \stdClass();

                return $this->get($key, $missing) !== $missing;
            }

            /**
             * Retrieve every configuration value.
             *
             * @return array
             */
            public function all()
            {
                return $this->values;
            }

            /**
             * Load environment variables from a .env file.
             *
             * Supported syntax:
             *   # comment
             *   NAME=value               # inline comment (only when preceded by a space)
             *   export NAME=value
             *   NAME='literal $value'    — no escapes nor interpolation
             *   NAME="line\nbreak ${X}"  — escapes (\n \r \t \" \\ \$) and interpolation,
             *                              the value can span multiple lines
             *   NAME=${X}/path           — interpolation in unquoted values too
             *
             * Variables already defined in the environment are kept unless `$overwrite` is true.
             * Values are exposed through getenv(), $_ENV and $_SERVER.
             *
             * @param  string                    $path      Path of the .env file
             * @param  bool                      $overwrite Replace variables already defined
             * @return array                     Variables defined by the file
             * @throws \InvalidArgumentException If the file contains an invalid line
             * @throws \RuntimeException         If the file is not readable
             */
            public function loadEnv($path, $overwrite = false)
            {
                if (!is_file($path) || !is_readable($path)) {
                    throw new \RuntimeException('Environment file not readable: '.$path);
                }

                $lines = preg_split('/\r\n|\r|\n/', file_get_contents($path));
                $count = count($lines);
                $defined = [];

                for ($i = 0; $i < $count; ++$i) {
                    $number = $i + 1;
                    $line = trim($lines[$i]);

                    if ('' === $line || '#' === $line[0]) {
                        continue;
                    }

                    if (!preg_match('/^(?:export\s+)?([A-Za-z_][A-Za-z0-9_.]*)\s*=\s*(.*)$/', $line, $match)) {
                        throw new \InvalidArgumentException(sprintf('Invalid line %d in %s', $number, $path));
                    }

                    $name = $match[1];
                    $raw = $match[2];
                    $quote = '' !== $raw && ('"' === $raw[0] || "'" === $raw[0]) ? $raw[0] : null;

                    if (null === $quote) {
                        // inline comments require a leading whitespace, so "a#b" is a valid value
                        $value = $this->interpolate(trim(preg_replace('/\s+#.*$/', '', $raw)), $defined);
                    } else {
                        $pattern = '"' === $quote
                            ? '/^"((?:[^"\\\\]|\\\\.)*)"\s*(?:#.*)?$/s'
                            : "/^'([^']*)'\\s*(?:#.*)?$/s";

                        // quoted values can span multiple lines until the closing quote
                        while (!preg_match($pattern, $raw, $quoted)) {
                            if (++$i >= $count) {
                                throw new \InvalidArgumentException(
                                    sprintf('Unclosed quote at line %d in %s', $number, $path)
                                );
                            }

                            $raw .= "\n".rtrim($lines[$i]);
                        }

                        $value = '"' === $quote
                            ? $this->interpolate($quoted[1], $defined, true)
                            : $quoted[1];
                    }

                    if (!$overwrite && false !== $this->lookup($name, [])) {
                        $defined[$name] = $this->lookup($name, []);

                        continue;
                    }

                    $defined[$name] = $value;

                    putenv($name.'='.$value);
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }

                return $defined;
            }

            /**
             * Retrieve an environment variable.
             *
             * The strings "true", "false", "null" and "empty" (case insensitive,
             * optionally wrapped in parentheses) are converted to true, false, null and "".
             *
             * @param  string $name    Variable name
             * @param  mixed  $default Value returned when the variable is not defined
             * @return mixed
             */
            public function env($name, $default = null)
            {
                $value = $this->lookup($name, []);

                if (false === $value) {
                    return $default;
                }

                switch (strtolower(trim($value, '()'))) {
                    case 'true':
                        return true;

                    case 'false':
                        return false;

                    case 'null':
                        return null;

                    case 'empty':
                        return '';
                }

                return $value;
            }

            /**
             * Find a variable among the given ones and the environment.
             *
             * @param  string       $name    Variable name
             * @param  array        $defined Variables defined by the file being loaded
             * @return string|false
             */
            private function lookup($name, array $defined)
            {
                if (array_key_exists($name, $defined)) {
                    return $defined[$name];
                }

                // $_ENV is empty when variables_order doesn't contain "E"
                if (isset($_ENV[$name]) && is_string($_ENV[$name])) {
                    return $_ENV[$name];
                }

                if (isset($_SERVER[$name]) && is_string($_SERVER[$name])) {
                    return $_SERVER[$name];
                }

                return getenv($name);
            }

            /**
             * Replace ${NAME} references and, for double quoted values, escape sequences.
             *
             * @param  string $value   Raw value
             * @param  array  $defined Variables defined by the file being loaded
             * @param  bool   $escapes Whether escape sequences have to be converted
             * @return string
             */
            private function interpolate($value, array $defined, $escapes = false)
            {
                $pattern = $escapes
                    ? '/\\\\(.)|\$\{([A-Za-z_][A-Za-z0-9_.]*)\}/s'
                    : '/()\$\{([A-Za-z_][A-Za-z0-9_.]*)\}/';

                return preg_replace_callback($pattern, function ($match) use ($defined) {
                    if (isset($match[2]) && '' !== $match[2]) {
                        $found = $this->lookup($match[2], $defined);

                        return false === $found ? '' : $found;
                    }

                    $map = ['n' => "\n", 'r' => "\r", 't' => "\t"];

                    // unknown sequences (e.g. \" \\ \$) are replaced by the escaped character
                    return $map[$match[1]] ?? $match[1];
                }, $value);
            }

            /**
             * Parse a configuration file.
             *
             * @param  string                    $path File path
             * @return array
             * @throws \InvalidArgumentException If the file type is unsupported or doesn't contain an array
             * @throws \RuntimeException         If the file is not readable or can't be parsed
             */
            private function read($path)
            {
                if (!is_file($path) || !is_readable($path)) {
                    throw new \RuntimeException('Configuration file not readable: '.$path);
                }

                switch (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
                    case 'php':
                        $values = (static function () use ($path) {
                            return require $path;
                        })();

                        break;

                    case 'json':
                        $values = json_decode(file_get_contents($path), true);

                        if (JSON_ERROR_NONE !== json_last_error()) {
                            throw new \RuntimeException(
                                sprintf('Invalid JSON in %s: %s', $path, json_last_error_msg())
                            );
                        }

                        break;

                    case 'ini':
                        $values = @parse_ini_file($path, true, INI_SCANNER_TYPED);

                        if (false === $values) {
                            throw new \RuntimeException('Invalid INI file: '.$path);
                        }

                        break;

                    default:
                        throw new \InvalidArgumentException('Unsupported configuration file: '.$path);
                }

                if (!is_array($values)) {
                    throw new \InvalidArgumentException('Configuration file must contain an array: '.$path);
                }

                return $values;
            }

            /**
             * Merge configuration arrays recursively.
             *
             * @param  array $base     Current values
             * @param  array $override New values
             * @return array
             */
            private function merge(array $base, array $override)
            {
                foreach ($override as $key => $value) {
                    if (
                        is_array($value) && !array_is_list($value) &&
                        isset($base[$key]) && is_array($base[$key]) && !array_is_list($base[$key])
                    ) {
                        $value = $this->merge($base[$key], $value);
                    }

                    $base[$key] = $value;
                }

                return $base;
            }
        };

        /**
         * Simple container implementation based on PSR-11,
         * implemented without breaking crystal rules.
         *
         * @link https://www.php-fig.org/psr/psr-11/
         */
        $deps['container'] = $deps['container'] ?? new class ($deps) {
            /**
             * @var array
             */
            private static $deps = [];

            /**
             * @var string[]
             */
            private static $aliases = [];

            /**
             * @param array $deps Dynamic set of dependencies.
             */
            public function __construct(array &$deps = [])
            {
                static::$deps = & $deps;
            }

            /**
             * Finds an entry of the container by its identifier and returns it.
             *
             * @param string $id   Identifier of the entry to look for.
             * @param mixed  $args Optional arguments for the service factory.
             *
             * @throws Exception No entry was found for **this** identifier.
             *
             * @return mixed Entry.
             */
            public function get(string $id, ...$args)
            {
                if (!$this->has($id)) {
                    throw new Exception(sprintf('Entry "%s" not found.', $id));
                }

                if (isset(static::$aliases[$id])) {
                    $id = static::$aliases[$id];
                }

                $service = static::$deps[$id];

                return is_callable($service)
                    ? call_user_func_array($service, $args)
                    : $service;
            }

            /**
             * Returns true if the container can return an entry for the given identifier.
             * Returns false otherwise.
             *
             * `has($id)` returning true does not mean that `get($id)` will not throw an exception.
             * It does however mean that `get($id)` will not throw a `NotFoundExceptionInterface`.
             *
             * @param string $id Identifier of the entry to look for.
             *
             * @return bool
             */
            public function has(string $id): bool
            {
                return isset(static::$deps[$id]) || isset(static::$aliases[$id]);
            }

            public function set(string $id, $value)
            {
                static::$deps[$id] = $value;
            }

            public function alias(string $alias, string $id)
            {
                static::$aliases[$alias] = $id;
            }

            public function make(string $fqcn, ...$args)
            {
                if (isset(static::$aliases[$fqcn])) {
                    $fqcn = static::$aliases[$fqcn];
                }

                if ($this->has($fqcn)) {
                    return $this->get($fqcn, ...$args);
                }

                if (!class_exists($fqcn)) {
                    throw new Exception(sprintf('Class "%s" does not exist.', $fqcn));
                }

                $reflectionClass = new ReflectionClass($fqcn);
                $constructor = $reflectionClass->getConstructor();

                if (empty($constructor)) {
                    return static::$deps[$fqcn] = $reflectionClass->newInstance();
                }

                $parameters = $constructor->getParameters();

                if (empty($parameters)) {
                    return static::$deps[$fqcn] = $reflectionClass->newInstance();
                }

                $resolvedParams = [];

                foreach ($parameters as $parameter) {
                    $type = $parameter->getType();
                    $type = method_exists($type, 'getName')
                        ? $type->getName()
                        : (string) $type;

                    $name = $parameter->name;

                    if (isset($args[$name])) {
                        $resolvedParams[] = $args[$name];

                        continue;
                    }

                    if ($this->has($type) || class_exists($type)) {
                        $resolvedParams[] = $this->make($type);

                        continue;
                    }

                    if ($parameter->isOptional()) {
                        $parameter->getDefaultValue();
                    }
                }

                return static::$deps[$fqcn] = $reflectionClass->newInstanceArgs($resolvedParams);
            }
        };

        /**
         * Exposes a Cross-Origin Resource Sharing middleware factory via $deps['cors'].
         *
         * @see https://fetch.spec.whatwg.org/#http-cors-protocol
         */
        $deps['cors'] = new class ($this) {
            /**
             * Framework instance, used to resolve other services.
             *
             * @var callable
             */
            private $mf;

            public function __construct(callable $mf)
            {
                $this->mf = $mf;
            }

            /**
             * Create the CORS middleware.
             *
             * Options:
             *   origins     — allowed origins, '*' or a list (default '*'), `*.example.com` wildcards allowed
             *   methods     — allowed methods (default GET, HEAD, POST, PUT, PATCH, DELETE)
             *   headers     — allowed request headers (default: the requested ones)
             *   expose      — response headers exposed to the client
             *   credentials — allow cookies and authorization headers (default false)
             *   max_age     — seconds preflight responses can be cached (default 600)
             *
             * @return callable
             */
            public function middleware(array $options = [])
            {
                $options += [
                    'origins' => '*',
                    'methods' => ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'],
                    'headers' => null,
                    'expose' => [],
                    'credentials' => false,
                    'max_age' => 600,
                ];

                return function ($request, callable $next) use ($options) {
                    $origin = $request->header('Origin');

                    if (is_null($origin) || !$this->isAllowed($origin, $options['origins'])) {
                        return $next($request);
                    }

                    $headers = [
                        'Access-Control-Allow-Origin' => '*' === $options['origins'] && !$options['credentials'] ? '*' : $origin,
                        'Vary' => 'Origin',
                    ];

                    if ($options['credentials']) {
                        $headers['Access-Control-Allow-Credentials'] = 'true';
                    }

                    // preflight request
                    if ('OPTIONS' === $request->method() && !is_null($request->header('Access-Control-Request-Method'))) {
                        $headers['Access-Control-Allow-Methods'] = implode(', ', (array) $options['methods']);
                        $allowed = is_null($options['headers'])
                            ? $request->header('Access-Control-Request-Headers')
                            : implode(', ', (array) $options['headers']);

                        if (!empty($allowed)) {
                            $headers['Access-Control-Allow-Headers'] = $allowed;
                        }

                        $headers['Access-Control-Max-Age'] = (string) $options['max_age'];

                        return call_user_func($this->mf, 'response')->noContent($headers);
                    }

                    if (!empty($options['expose'])) {
                        $headers['Access-Control-Expose-Headers'] = implode(', ', (array) $options['expose']);
                    }

                    $response = $next($request);

                    foreach ($headers as $name => $value) {
                        $response = 'Vary' === $name ? $response->withAddedHeader($name, $value) : $response->withHeader($name, $value);
                    }

                    return $response;
                };
            }

            /**
             * @param string          $origin  Request origin
             * @param string|string[] $allowed Allowed origins
             */
            private function isAllowed(string $origin, $allowed)
            {
                if ('*' === $allowed) {
                    return true;
                }

                foreach ((array) $allowed as $pattern) {
                    if ($pattern === $origin) {
                        return true;
                    }

                    $regex = '#^'.str_replace('\*', '[a-z0-9-]+', preg_quote($pattern, '#')).'$#i';

                    if (false !== strpos($pattern, '*') && preg_match($regex, $origin)) {
                        return true;
                    }
                }

                return false;
            }
        };

        $deps['database'] = new class () {
            /**
             * Shared PDO instance.
             *
             * @var PDO
             */
            private static $pdo;

            /**
             * Set a shared PDO connection.
             */
            public static function setPdo(PDO $pdo)
            {
                static::$pdo = $pdo;
                static::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                static::$pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);
            }

            /**
             * Execute a query and parse result rows as array.
             */
            public function query(string $sql, array $params = [])
            {
                $stmt = static::$pdo->prepare($sql);
                $stmt->execute($params);

                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            /**
             * A simple query builder, parsing all the results as array of array.
             *
             * @param array|string              $fields
             * @param array|integer|string|null $where
             * @param string|null               $order
             * @param string|null               $limit
             */
            public function select(string $table, $fields = '*', $where = null, $order = null, $limit = null)
            {
                if (empty($fields)) {
                    $fields = '*';
                }

                if (is_scalar($fields) && preg_match('/[a-zA-Z0-9\_]+/', $fields)) {
                    $fields = [$fields];
                }

                if (is_array($fields)) {
                    $fields = join(', ', array_map(function ($field) {
                        return preg_match('/^[a-zA-Z0-9\_]+$/', $field)
                            ? sprintf('`%s`', $field)
                            : $field;
                    }, $fields));
                }

                $sql = sprintf('SELECT %s FROM `%s`', $fields, $table);

                $params = [];

                if (!empty($where)) {
                    $where = $this->buildWhereCondition($where);

                    $params += $where['params'];
                    $sql .= sprintf(' WHERE %s', $where['sql']);
                }

                if (!empty($order)) {
                    $sql .= sprintf(' ORDER BY %s', $order);
                }

                if (!empty($limit)) {
                    $sql .= sprintf(' LIMIT %s', $limit);
                }

                return $this->query($sql, $params);
            }

            /**
             * Given an array of columns and values, creates a row in the table.
             *
             * @return integer|string
             */
            public function create(string $table, array $values)
            {
                $keys = array_keys($values);
                $sql = sprintf(
                    'INSERT INTO `%s` (%s) VALUES (:%s)',
                    $table,
                    implode(', ', $keys),
                    implode(', :', $keys)
                );

                $this->query($sql, $values);

                $lastInsertId = static::$pdo->lastInsertId();

                return is_numeric($lastInsertId)
                    ? (int) $lastInsertId
                    : $lastInsertId;
            }

            /**
             * Retrieve a single record.
             */
            public function read(string $table, $where, $key = 'id')
            {
                $where = $this->buildWhereCondition($where, $key);
                $stmt = static::$pdo->prepare(sprintf('SELECT * FROM `%s` WHERE %s LIMIT 1', $table, $where['sql']));
                $stmt->execute($where['params']);

                return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            /**
             * Update one or more records based on the shared conditions system.
             */
            public function update(string $table, array $values, $where, $key = 'id')
            {
                $params = $values;
                $keys = array_keys($values);

                $set = join(', ', array_map(function ($key) {
                    return sprintf('`%s` = :%s', $key, $key);
                }, $keys));

                $where = $this->buildWhereCondition($where, $key);
                $stmt = static::$pdo->prepare(sprintf('UPDATE `%s` SET %s WHERE %s', $table, $set, $where['sql']));

                return $stmt->execute(array_merge($where['params'], $params));
            }

            /**
             * Delete one or more records based on the shared conditions system.
             */
            public function delete(string $table, $where, $key = 'id')
            {
                $where = $this->buildWhereCondition($where, $key);
                $stmt = static::$pdo->prepare(sprintf('DELETE FROM `%s` WHERE %s', $table, $where['sql']));

                return $stmt->execute($where['params']);
            }

            // === TRANSACTIONS ===
            /**
             * @link https://www.php.net/manual/en/pdo.begintransaction.php
             */
            public function beginTransaction()
            {
                return static::$pdo->beginTransaction();
            }

            /**
             * @link https://www.php.net/manual/en/pdo.commit.php
             */
            public function commit()
            {
                return static::$pdo->commit();
            }

            /**
             * @link https://www.php.net/manual/en/pdo.rollback.php
             */
            public function rollBack()
            {
                return static::$pdo->rollBack();
            }

            /**
             * @link https://www.php.net/manual/en/pdo.intransaction.php
             */
            public function inTransaction()
            {
                return static::$pdo->inTransaction();
            }

            /**
             * Grouping of database operations, this allows their executions
             * without making changes if one of them fails.
             *
             * @return mixed The output of the callback.
             */
            public function transactional(callable $callback)
            {
                $this->beginTransaction();

                try {
                    $output = $callback();

                    $this->commit();

                    return $output;
                } catch (Throwable $exception) {
                    $this->rollBack();

                    throw $exception;
                }
            }

            private function buildWhereCondition(): array
            {
                $value = func_get_arg(0);

                if (1 === func_num_args()) {
                    if (empty($value)) {
                        return [
                            'params' => [],
                            'sql' => ''
                        ];
                    }

                    // 1 => `id` = 1
                    // 'foo' => `id` = "foo"
                    if (is_scalar($value) || is_numeric($value)) {
                        return [
                            'params' => [
                                ':id' => $value
                            ],
                            'sql' => '`id` = :id',
                        ];
                    }

                    if (array_is_list($value)) {
                        // [1, 'foo'] => `id` IN (1, "foo")
                        return [
                            'params' => [
                                ':id' => $value
                            ],
                            'sql' => '`id` IN (:id)',
                        ];
                    }

                    if (is_array($value)) {
                        // ['field1 >= ? AND field1 <= ?' => [42, 100], 'field2 LIKE ?' => '%test%'] => (field1 >= ? AND field1 <= ?) AND field2 LIKE ?
                        // ['bar' => 'foo', 'lorem' => 'ipsum'] => `bar` = "foo" AND `lorem` = "ipsum"
                        $sql = '';
                        $params = [];

                        foreach ($value as $k => $v) {
                            if (preg_match('/^[a-zA-Z0-9\_]+$/', $k)) {
                                $v = [$k => $v];
                                $k = sprintf('`%s` = :%s', $k, $k);
                            }

                            if (!empty($sql)) {
                                $sql .= ' AND ';
                            }

                            $sql .= sprintf('(%s)', $k);

                            if (is_scalar($v)) {
                                $v = [$v];
                            }

                            if (array_is_list($v)) {
                                array_push($params, ...$v);

                                continue;
                            }

                            $params += $v;
                        }

                        return [
                            'params' => $params,
                            'sql' => $sql,
                        ];
                    }
                }

                if (2 === func_num_args()) {
                    $key = func_get_arg(1);
                    $prefixed = ':'.$key;

                    if (is_array($value) && array_is_list($value)) {
                        // [1, 2], 'bar' => `bar` IN (1, 2)
                        return [
                            'params' => [
                                $prefixed => $value
                            ],
                            'sql' => sprintf('`%s` IN (%s)', $key, $prefixed),
                        ];
                    }

                    // 'foo', 'bar' => `bar` = "foo"
                    return [
                        'params' => [
                            $prefixed => $value
                        ],
                        'sql' => sprintf('`%s` = %s', $key, $prefixed),
                    ];
                }
            }
        };

        /**
         * Exposes HTTP errors handling via $deps['errors'].
         *
         * Errors are rendered as Problem Details (RFC 9457) JSON documents,
         * or as a minimal HTML page when the client accepts text/html.
         *
         * @see https://www.rfc-editor.org/rfc/rfc9457
         */
        $deps['errors'] = new class ($this) {
            /**
             * Framework instance, used to resolve other services.
             *
             * @var callable
             */
            private $mf;

            /**
             * Custom handler `function (Throwable $e, $request)` returning a response.
             *
             * @var callable|null
             */
            private $handler;

            /**
             * Expose exception details in 500 responses.
             *
             * @var bool
             */
            private $debug = false;

            /**
             * One of: auto, json, html.
             *
             * @var string
             */
            private $format = 'auto';

            public function __construct(callable $mf)
            {
                $this->mf = $mf;
            }

            /**
             * Create an exception rendered with the given status code.
             *
             * @param int            $status     HTTP status code
             * @param string         $detail     Explanation of the problem
             * @param array          $extensions Additional Problem Details members
             * @param array          $headers    Additional response headers
             * @param Throwable|null $previous   Previous exception
             *
             * @return RuntimeException
             */
            public function create(int $status, string $detail = '', array $extensions = [], array $headers = [], $previous = null)
            {
                return new class ($status, $detail, $extensions, $headers, $previous) extends RuntimeException {
                    private $status;
                    private $extensions;
                    private $headers;

                    public function __construct(int $status, string $detail, array $extensions, array $headers, $previous)
                    {
                        parent::__construct($detail, $status, $previous);

                        $this->status = $status;
                        $this->extensions = $extensions;
                        $this->headers = $headers;
                    }

                    public function getStatusCode()
                    {
                        return $this->status;
                    }

                    public function getExtensions()
                    {
                        return $this->extensions;
                    }

                    public function getHeaders()
                    {
                        return $this->headers;
                    }
                };
            }

            /**
             * Throw an exception rendered with the given status code.
             *
             * @throws RuntimeException
             */
            public function abort(int $status, string $detail = '', array $extensions = [], array $headers = [])
            {
                throw $this->create($status, $detail, $extensions, $headers);
            }

            /**
             * Set a custom handler, returning null falls back to the default rendering.
             */
            public function setHandler($handler)
            {
                if (!is_null($handler) && !is_callable($handler)) {
                    throw new InvalidArgumentException('Error handler must be callable or null');
                }

                $this->handler = $handler;
            }

            public function setDebug(bool $debug)
            {
                $this->debug = $debug;
            }

            /**
             * @param string $format One of: auto, json, html
             */
            public function setFormat(string $format)
            {
                if (!in_array($format, ['auto', 'json', 'html'])) {
                    throw new InvalidArgumentException(sprintf('Unknown errors format "%s"', $format));
                }

                $this->format = $format;
            }

            /**
             * Create the response for an error status code.
             *
             * @param int         $status     HTTP status code
             * @param string|null $detail     Explanation of the problem
             * @param array       $extensions Additional Problem Details members
             * @param array       $headers    Additional response headers
             *
             * @return object
             */
            public function response(int $status, $detail = null, array $extensions = [], array $headers = [])
            {
                $factory = call_user_func($this->mf, 'response');
                $format = $this->format;

                if ('auto' === $format) {
                    $request = call_user_func($this->mf, 'request');
                    $format = !$request->wantsJson() && $request->accepts('text/html') ? 'html' : 'json';
                }

                if ('json' === $format) {
                    return $factory->problem($status, $detail, $extensions, $headers);
                }

                $title = htmlspecialchars($status.' '.$factory->reasonPhrase($status), ENT_QUOTES, 'UTF-8');
                $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>'.$title.'</title></head><body><h1>'.$title.'</h1>';

                if (!is_null($detail) && '' !== $detail) {
                    $html .= '<p>'.htmlspecialchars($detail, ENT_QUOTES, 'UTF-8').'</p>';
                }

                return $factory->html($html.'</body></html>', $status, $headers);
            }

            /**
             * Convert an exception to a response.
             *
             * Exceptions exposing getStatusCode() are rendered with their status code,
             * other exceptions are logged and rendered as 500 errors.
             *
             * @return object
             */
            public function render(Throwable $exception, $request = null)
            {
                if (!is_null($this->handler)) {
                    $response = call_user_func($this->handler, $exception, $request ?: call_user_func($this->mf, 'request'));

                    if (!is_null($response)) {
                        $factory = call_user_func($this->mf, 'response');

                        return $factory->isResponse($response) ? $response : $factory->json($response);
                    }
                }

                if (method_exists($exception, 'getStatusCode') && is_int($status = $exception->getStatusCode()) && $status >= 400 && $status < 600) {
                    return $this->response(
                        $status,
                        $exception->getMessage(),
                        method_exists($exception, 'getExtensions') ? $exception->getExtensions() : [],
                        method_exists($exception, 'getHeaders') ? $exception->getHeaders() : []
                    );
                }

                call_user_func($this->mf, 'logger')->error('Uncaught {class}: {message} in {file}:{line}', [
                    'class' => get_class($exception),
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ]);

                if (!$this->debug) {
                    return $this->response(500);
                }

                return $this->response(500, $exception->getMessage(), [
                    'exception' => [
                        'class' => get_class($exception),
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                        'trace' => explode("\n", $exception->getTraceAsString()),
                    ],
                ]);
            }
        };

        $deps['listener'] = new class () {
            /**
             * @var callable[]
             */
            private $listeners = [];

            /**
             * Provide all relevant listeners with an event to process.
             *
             * @param object $event The object to process.
             *
             * @return object The Event that was passed, now modified by listeners.
             */
            public function dispatch($event)
            {
                $eventName = $this->getEventName($event);
                $listeners = $this->getListenersForEvent($event);

                foreach ($listeners as $listener) {
                    $event = $listener($event, $eventName, $this);
                }

                return $event;
            }

            public function on(string $eventName, callable $callback)
            {
                if (empty($this->listeners[$eventName])) {
                    $this->listeners[$eventName] = [];
                }

                $this->listeners[$eventName][] = $callback;
            }

            public function off(string $eventName)
            {
                $this->listeners[$eventName] = [];
            }

            /**
             * @param object $event An event for which to return the relevant listeners.
             *
             * @return iterable<callable> An iterable (array, iterator, or generator) of callables. Each callable MUST be type-compatible with $event.
             */
            public function getListenersForEvent($event)
            {
                return $this->listeners[$this->getEventName($event)] ?? [];
            }

            private function getEventName($event)
            {
                if (!is_object($event)) {
                    return $event;
                }

                if (method_exists($event, 'getCrystalEventName')) {
                    return $event->getCrystalEventName();
                }

                if (method_exists($event, 'getEventName')) {
                    return $event->getEventName();
                }

                return get_class($event);
            }
        };

        // logger based on PSR-3
        // https://www.php-fig.org/psr/psr-3/
        $deps['logger'] = new class () {
            /**
             * Possible level values.
             */
            private $logLevels = [
                'emerg' => LOG_EMERG,
                'emergency' => LOG_EMERG,
                'alert' => LOG_ALERT,
                'crit' => LOG_CRIT,
                'critical' => LOG_CRIT,
                'err' => LOG_ERR,
                'error' => LOG_ERR,
                'warn' => LOG_WARNING,
                'warning' => LOG_WARNING,
                'notice' => LOG_NOTICE,
                'info' => LOG_INFO,
                'debug' => LOG_DEBUG,
            ];

            /**
             * Custom logger implementation, if defined.
             *
             * @var callable|null
             */
            private $implementation;

            /**
             * Additional context for every message.
             *
             * @var array
             */
            private $context = [];

            private function interpolate($message, array $context = [])
            {
                // build a replacement array with braces around the context keys
                $replace = [];

                foreach ($context as $key => $val) {
                    // check that the value can be cast to string
                    if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                        $replace['{' . $key . '}'] = $val;
                    }
                }

                // interpolate replacement values into the message and return
                return strtr($message, $replace);
            }

            /**
             * Add shared context value.
             *
             * @param string $key
             * @param mixed  $value
             */
            public function addContext(string $key, $value)
            {
                $this->context[$key] = $value;
            }

            /**
             * Set all the shared context.
             *
             * @param array $context
             */
            public function setContext(array $context)
            {
                $this->context = $context;
            }

            /**
             * Get all the shared context.
             *
             * @return array
             */
            public function getContext(): array
            {
                return $this->context;
            }

            /**
             * Unset all the values in the shared context.
             */
            public function resetContext()
            {
                $this->context = [];
            }

            /**
             * Remove specific keys from the shared context.
             *
             * @param string $keys
             */
            public function unsetContext(string ...$keys)
            {
                foreach ($keys as $key) {
                    unset($this->context[$key]);
                }
            }

            /**
             * System is unusable.
             *
             * @param string $message
             * @param array  $context
             *
             * @return void
             */
            public function emergency($message, $context = [])
            {
                $this->log('emergency', $message, $context);
            }

            /**
             * Action must be taken immediately.
             *
             * Example: Entire website down, database unavailable, etc. This should
             * trigger the SMS alerts and wake you up.
             *
             * @param string $message
             * @param array  $context
             *
             * @return void
             */
            public function alert($message, $context = [])
            {
                $this->log('alert', $message, $context);
            }

            /**
             * Critical conditions.
             *
             * Example: Application component unavailable, unexpected exception.
             *
             * @param string $message
             * @param array  $context
             *
             * @return void
             */
            public function critical($message, $context = [])
            {
                $this->log('critical', $message, $context);
            }

            /**
             * Runtime errors that do not require immediate action but should typically
             * be logged and monitored.
             *
             * @param string $message
             * @param array  $context
             *
             * @return void
             */
            public function error($message, $context = [])
            {
                $this->log('error', $message, $context);
            }

            /**
             * Exceptional occurrences that are not errors.
             *
             * Example: Use of deprecated APIs, poor use of an API, undesirable things
             * that are not necessarily wrong.
             *
             * @param string $message
             * @param array  $context
             *
             * @return void
             */
            public function warning($message, $context = [])
            {
                $this->log('warning', $message, $context);
            }

            /**
             * Normal but significant events.
             *
             * @param string $message
             * @param array  $context
             *
             * @return void
             */
            public function notice($message, $context = [])
            {
                $this->log('notice', $message, $context);
            }

            /**
             * Interesting events.
             *
             * Example: User logs in, SQL logs.
             *
             * @param string $message
             * @param array  $context
             *
             * @return void
             */
            public function info($message, $context = [])
            {
                $this->log('info', $message, $context);
            }

            /**
             * Detailed debug information.
             *
             * @param string $message
             * @param array  $context
             *
             * @return void
             */
            public function debug($message, $context = [])
            {
                $this->log('debug', $message, $context);
            }

            /**
             * Logs with an arbitrary level.
             *
             * @param mixed  $level
             * @param string $message
             * @param array  $context
             *
             * @return void
             */
            public function log($level, $message, $context = [])
            {
                $context = array_merge($this->context, $context);
                $message = $this->interpolate($message, $context);

                if (!empty($this->implementation)) {
                    return call_user_func_array($this->implementation, [
                        $level,
                        $message,
                        $context
                    ]);
                }

                syslog(
                    $this->logLevels[mb_strtolower($level)] ?? $level,
                    $message.' '.json_encode($context)
                );
            }

            /**
             * Sets a custom implementation instead of the default syslog.
             *
             * @param callable $implementation
             */
            public function setImplementation(callable $implementation)
            {
                $this->implementation = $implementation;
            }

            /**
             * Resets the implementation to the default one (using syslog).
             */
            public function resetImplementation()
            {
                $this->implementation = null;
            }
        };

        /**
         * Exposes a mailer object via $deps['mailer'] to compose and send emails.
         *
         * Messages are arrays supporting the keys:
         * from, to, cc, bcc, reply_to, subject, text, html, headers, attachments.
         *
         * Drivers: mail (default), smtp, custom.
         *
         * @see https://www.php.net/manual/en/function.mail.php
         * @see https://www.rfc-editor.org/rfc/rfc5321 (SMTP)
         * @see https://www.rfc-editor.org/rfc/rfc2045 (MIME)
         */
        $deps['mailer'] = new class () {
            /**
             * Active driver instance.
             *
             * @var object|null
             */
            private $driver;

            /**
             * Values merged into every message (e.g. a default "from").
             *
             * @var array
             */
            private $defaults = [];

            /**
             * Configure the mailer driver.
             *
             * Built-in drivers:
             *   setDriver('mail')          — PHP mail() function (default)
             *   setDriver('smtp', $config) — SMTP server, $config keys:
             *                                host, port, username, password,
             *                                encryption (null|'ssl'|'tls'), timeout
             *
             * Custom driver (must implement send):
             *   setDriver($customObject)
             *
             * Drivers receive the composed message as an array with the keys:
             * from, recipients, subject, headers and body.
             *
             * @param  string|object             $driver Driver name or custom driver object
             * @param  mixed                     $config Driver-specific configuration
             * @throws \InvalidArgumentException If driver is unknown or configuration is invalid
             * @throws \RuntimeException         If driver cannot be initialised
             */
            public function setDriver($driver, $config = null)
            {
                if (is_object($driver)) {
                    if (!method_exists($driver, 'send')) {
                        throw new \InvalidArgumentException('Custom driver is missing method: send');
                    }

                    $this->driver = $driver;

                    return;
                }

                switch ($driver) {
                    case 'mail':
                        if (!function_exists('mail')) {
                            throw new \RuntimeException('The mail() function is not available');
                        }

                        $this->driver = new class () {
                            public function send(array $message)
                            {
                                $headers = $message['headers'];
                                $to = $headers['To'] ?? 'undisclosed-recipients:;';
                                $subject = $headers['Subject'];

                                // mail() adds "To" and "Subject" by itself
                                unset($headers['To'], $headers['Subject']);

                                $raw = '';

                                foreach ($headers as $name => $value) {
                                    $raw .= $name.': '.$value."\r\n";
                                }

                                // the envelope sender is required by some MTAs to avoid spoofing warnings
                                return mail($to, $subject, $message['body'], rtrim($raw), '-f'.$message['from']);
                            }
                        };

                        break;

                    case 'smtp':
                        if (!is_array($config) || empty($config['host'])) {
                            throw new \InvalidArgumentException(
                                'smtp driver requires an array with at least the "host" key as second argument'
                            );
                        }

                        $config = array_merge([
                            'port' => 25,
                            'username' => null,
                            'password' => null,
                            'encryption' => null,
                            'timeout' => 30,
                        ], $config);

                        if (!in_array($config['encryption'], [null, 'ssl', 'tls'], true)) {
                            throw new \InvalidArgumentException('Unknown SMTP encryption: '.$config['encryption']);
                        }

                        if (null !== $config['encryption'] && !extension_loaded('openssl')) {
                            throw new \RuntimeException('The openssl extension is required for SMTP encryption');
                        }

                        $this->driver = new class ($config) {
                            private $config;

                            private $socket;

                            public function __construct(array $config)
                            {
                                $this->config = $config;
                            }

                            public function send(array $message)
                            {
                                $config = $this->config;
                                $transport = 'ssl' === $config['encryption'] ? 'ssl' : 'tcp';

                                $this->socket = @stream_socket_client(
                                    $transport.'://'.$config['host'].':'.$config['port'],
                                    $errno,
                                    $errstr,
                                    $config['timeout']
                                );

                                if (false === $this->socket) {
                                    throw new \RuntimeException(sprintf('SMTP connection failed: %s (%d)', $errstr, $errno));
                                }

                                stream_set_timeout($this->socket, $config['timeout']);

                                try {
                                    $this->expect(220);

                                    // the domain of the sender identifies the client
                                    $client = substr(strrchr($message['from'], '@'), 1);
                                    $this->command('EHLO '.$client, 250);

                                    if ('tls' === $config['encryption']) {
                                        $this->command('STARTTLS', 220);

                                        if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                                            throw new \RuntimeException('SMTP STARTTLS negotiation failed');
                                        }

                                        // capabilities must be requested again after the TLS handshake
                                        $this->command('EHLO '.$client, 250);
                                    }

                                    if (null !== $config['username']) {
                                        $this->command('AUTH LOGIN', 334);
                                        $this->command(base64_encode($config['username']), 334);
                                        $this->command(base64_encode((string) $config['password']), 235);
                                    }

                                    $this->command('MAIL FROM:<'.$message['from'].'>', 250);

                                    foreach ($message['recipients'] as $recipient) {
                                        $this->command('RCPT TO:<'.$recipient.'>', [250, 251]);
                                    }

                                    $this->command('DATA', 354);

                                    $raw = '';

                                    foreach ($message['headers'] as $name => $value) {
                                        $raw .= $name.': '.$value."\r\n";
                                    }

                                    $raw .= "\r\n".$message['body'];

                                    // dot-stuffing, lines starting with a dot must be escaped
                                    // https://www.rfc-editor.org/rfc/rfc5321#section-4.5.2
                                    $raw = preg_replace('/^\./m', '..', $raw);

                                    $this->command($raw."\r\n.", 250);
                                    $this->command('QUIT', 221);
                                } finally {
                                    fclose($this->socket);
                                }

                                return true;
                            }

                            /**
                             * Write a command and check the response code.
                             *
                             * @param string    $command
                             * @param int|int[] $expected
                             */
                            private function command(string $command, $expected)
                            {
                                fwrite($this->socket, $command."\r\n");

                                return $this->expect($expected);
                            }

                            /**
                             * Read a (multiline) response and check its code.
                             *
                             * @param int|int[] $expected
                             *
                             * @throws \RuntimeException If the code isn't the expected one
                             *
                             * @return string
                             */
                            private function expect($expected)
                            {
                                $response = '';

                                // multiline responses use a dash after the code, e.g. "250-SIZE"
                                while (false !== ($line = fgets($this->socket, 515))) {
                                    $response .= $line;

                                    if (!isset($line[3]) || '-' !== $line[3]) {
                                        break;
                                    }
                                }

                                if (!in_array((int) substr($response, 0, 3), (array) $expected, true)) {
                                    throw new \RuntimeException('Unexpected SMTP response: '.trim($response));
                                }

                                return $response;
                            }
                        };

                        break;

                    default:
                        throw new \InvalidArgumentException("Unknown mailer driver: $driver");
                }
            }

            /**
             * Set values merged into every message, e.g. ['from' => 'noreply@example.com'].
             *
             * @param array $defaults
             */
            public function setDefaults(array $defaults)
            {
                $this->defaults = $defaults;
            }

            /**
             * Compose and send a message.
             *
             * Addresses (from, to, cc, bcc, reply_to) can be a string ('a@example.com'),
             * a list of strings or an associative array (['a@example.com' => 'Name']).
             * Attachments can be file paths or arrays with the keys
             * path or content, name and type.
             *
             * @param  array                     $message
             * @return bool                      Success, as reported by the driver
             * @throws \InvalidArgumentException If the message is invalid
             */
            public function send(array $message)
            {
                if (null === $this->driver) {
                    $this->setDriver('mail');
                }

                return (bool) $this->driver->send($this->compose($message));
            }

            /**
             * Build the message headers and the MIME body without sending it.
             *
             * @param  array                     $message
             * @return array                     from, recipients, subject, headers and body
             * @throws \InvalidArgumentException If the message is invalid
             */
            public function compose(array $message)
            {
                $message = array_merge([
                    'from' => null,
                    'to' => [],
                    'cc' => [],
                    'bcc' => [],
                    'reply_to' => [],
                    'subject' => '',
                    'text' => null,
                    'html' => null,
                    'headers' => [],
                    'attachments' => [],
                ], $this->defaults, $message);

                // normalizes addresses to email => name, rejecting invalid ones
                $normalize = function ($addresses, string $field) {
                    $result = [];

                    foreach ((array) $addresses as $key => $value) {
                        list($email, $name) = is_string($key) ? [$key, (string) $value] : [(string) $value, ''];

                        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            throw new \InvalidArgumentException(sprintf('Invalid "%s" address: %s', $field, $email));
                        }

                        $result[$email] = $name;
                    }

                    return $result;
                };

                // header values containing line breaks would allow headers injection
                $safe = function ($value, string $field) {
                    if (preg_match('/[\r\n]/', (string) $value)) {
                        throw new \InvalidArgumentException(sprintf('Invalid line break in "%s"', $field));
                    }

                    return (string) $value;
                };

                // non-ASCII values are encoded as described in RFC 2047
                $encode = function (string $value) {
                    return preg_match('/[^\x20-\x7e]/', $value)
                        ? '=?UTF-8?B?'.base64_encode($value).'?='
                        : $value;
                };

                $format = function (array $addresses) use ($encode, $safe) {
                    $formatted = [];

                    foreach ($addresses as $email => $name) {
                        $name = $safe($name, 'name');
                        $formatted[] = '' === $name
                            ? $email
                            : (preg_match('/[^\x20-\x7e]/', $name) ? $encode($name) : '"'.addcslashes($name, '"\\').'"').' <'.$email.'>';
                    }

                    return implode(', ', $formatted);
                };

                $from = $normalize($message['from'], 'from');

                if (1 !== count($from)) {
                    throw new \InvalidArgumentException('Exactly one "from" address is required');
                }

                $to = $normalize($message['to'], 'to');
                $cc = $normalize($message['cc'], 'cc');
                $bcc = $normalize($message['bcc'], 'bcc');
                $replyTo = $normalize($message['reply_to'], 'reply_to');
                $recipients = array_keys(array_merge($to, $cc, $bcc));

                if (empty($recipients)) {
                    throw new \InvalidArgumentException('At least one recipient is required');
                }

                if (null === $message['text'] && null === $message['html']) {
                    throw new \InvalidArgumentException('Message requires a "text" or "html" body');
                }

                $sender = key($from);

                $headers = [
                    'Date' => date('r'),
                    'From' => $format($from),
                ];

                if ($to) {
                    $headers['To'] = $format($to);
                }

                if ($cc) {
                    $headers['Cc'] = $format($cc);
                }

                if ($replyTo) {
                    $headers['Reply-To'] = $format($replyTo);
                }

                $headers['Subject'] = $encode($safe($message['subject'], 'subject'));
                $headers['Message-ID'] = sprintf('<%s@%s>', bin2hex(random_bytes(16)), substr(strrchr($sender, '@'), 1));
                $headers['MIME-Version'] = '1.0';

                foreach ($message['headers'] as $name => $value) {
                    if (!preg_match('/^[!-9;-~]+$/', $name)) {
                        throw new \InvalidArgumentException(sprintf('Invalid header name: %s', $name));
                    }

                    $headers[$name] = $safe($value, $name);
                }

                // each part is a [headers, body] pair, bodies use CRLF line endings
                $part = function (string $type, string $content) {
                    $content = preg_replace('/\r\n?|\n/', "\r\n", $content);

                    return [
                        'Content-Type: '.$type.'; charset=UTF-8'."\r\n".'Content-Transfer-Encoding: quoted-printable',
                        quoted_printable_encode($content),
                    ];
                };

                $multipart = function (string $subtype, array $parts) {
                    $boundary = '=_'.bin2hex(random_bytes(12));
                    $body = '';

                    foreach ($parts as $item) {
                        $body .= '--'.$boundary."\r\n".$item[0]."\r\n\r\n".$item[1]."\r\n";
                    }

                    return [
                        'Content-Type: multipart/'.$subtype.'; boundary="'.$boundary.'"',
                        $body.'--'.$boundary.'--',
                    ];
                };

                $alternatives = [];

                if (null !== $message['text']) {
                    $alternatives[] = $part('text/plain', (string) $message['text']);
                }

                if (null !== $message['html']) {
                    $alternatives[] = $part('text/html', (string) $message['html']);
                }

                $content = 1 === count($alternatives) ? $alternatives[0] : $multipart('alternative', $alternatives);

                if (!empty($message['attachments'])) {
                    $parts = [$content];

                    foreach ((array) $message['attachments'] as $attachment) {
                        if (is_string($attachment)) {
                            $attachment = ['path' => $attachment];
                        }

                        if (isset($attachment['path'])) {
                            if (!is_file($attachment['path']) || !is_readable($attachment['path'])) {
                                throw new \InvalidArgumentException(sprintf('Attachment not readable: %s', $attachment['path']));
                            }

                            $attachment['content'] = file_get_contents($attachment['path']);
                            $attachment['name'] = $attachment['name'] ?? basename($attachment['path']);
                        }

                        if (!isset($attachment['content'], $attachment['name'])) {
                            throw new \InvalidArgumentException('Attachments require a "path" or both "content" and "name"');
                        }

                        $name = $encode($safe($attachment['name'], 'attachment name'));
                        $type = $safe($attachment['type'] ?? 'application/octet-stream', 'attachment type');

                        $parts[] = [
                            'Content-Type: '.$type.'; name="'.$name.'"'."\r\n"
                            .'Content-Transfer-Encoding: base64'."\r\n"
                            .'Content-Disposition: attachment; filename="'.$name.'"',
                            rtrim(chunk_split(base64_encode($attachment['content']), 76, "\r\n")),
                        ];
                    }

                    $content = $multipart('mixed', $parts);
                }

                // the content headers of the root part become message headers
                foreach (explode("\r\n", $content[0]) as $line) {
                    list($name, $value) = explode(': ', $line, 2);
                    $headers[$name] = $value;
                }

                return [
                    'from' => $sender,
                    'recipients' => $recipients,
                    'subject' => (string) $message['subject'],
                    'headers' => $headers,
                    'body' => $content[1],
                ];
            }
        };

        /**
         * Database migrations built on top of the database service.
         *
         * Migrations are identified by a version string and sorted using the
         * natural order algorithm, so both `1`, `2`, `10` and timestamps
         * like `20240101120000_create_users` are supported.
         * Applied versions are tracked in a dedicated table (`crystal_migrations`
         * by default) created on first use.
         *
         * @see https://www.php.net/manual/en/function.strnatcmp.php
         */
        $deps['migrations'] = new class ($deps) {
            /**
             * Dynamic set of dependencies, used to retrieve the database service.
             *
             * @var array
             */
            private $deps;

            /**
             * Registered migrations, version => ['up' => callable, 'down' => callable|null].
             *
             * @var array
             */
            private $migrations = [];

            /**
             * Table used to track applied migrations.
             *
             * @var string
             */
            private $table = 'crystal_migrations';

            /**
             * @param array $deps Dynamic set of dependencies.
             */
            public function __construct(array &$deps = [])
            {
                $this->deps = &$deps;
            }

            /**
             * Change the table used to track applied migrations.
             */
            public function setTable(string $table)
            {
                $this->table = $table;
            }

            /**
             * Register a migration.
             *
             * Both callbacks receive the database service as first argument.
             *
             * @param string        $version Unique identifier of the migration
             * @param callable      $up      Callback applying the changes
             * @param callable|null $down    Callback reverting the changes
             *
             * @throws \InvalidArgumentException If the version is already registered or down isn't callable
             */
            public function add(string $version, callable $up, $down = null)
            {
                if (!is_null($down) && !is_callable($down)) {
                    throw new \InvalidArgumentException(sprintf('Migration "%s" down callback is not callable', $version));
                }

                if (isset($this->migrations[$version])) {
                    throw new \InvalidArgumentException(sprintf('Migration "%s" already registered', $version));
                }

                $this->migrations[$version] = [
                    'up' => $up,
                    'down' => $down,
                ];

                uksort($this->migrations, 'strnatcmp');
            }

            /**
             * Register all the migrations found in a directory.
             *
             * Each `*.php` file is a migration, the file name (without extension)
             * is the version and the file must return a callable (the `up` callback)
             * or an array with `up` and `down` keys.
             *
             * @throws \InvalidArgumentException If the directory or a migration is invalid
             */
            public function load(string $directory)
            {
                if (!is_dir($directory)) {
                    throw new \InvalidArgumentException(sprintf('Migrations directory not found: %s', $directory));
                }

                foreach (glob(rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*.php') as $file) {
                    $migration = require $file;

                    if (is_callable($migration)) {
                        $migration = ['up' => $migration];
                    }

                    if (!is_array($migration) || !isset($migration['up']) || !is_callable($migration['up'])) {
                        throw new \InvalidArgumentException(sprintf('Invalid migration file: %s', $file));
                    }

                    $this->add(basename($file, '.php'), $migration['up'], $migration['down'] ?? null);
                }
            }

            /**
             * List of applied versions, in the order they should be reverted.
             *
             * @return string[]
             */
            public function applied(): array
            {
                $this->createTable();

                $versions = array_column($this->database()->select($this->table, 'version'), 'version');

                usort($versions, 'strnatcmp');

                return $versions;
            }

            /**
             * List of registered versions not applied yet.
             *
             * @return string[]
             */
            public function pending(): array
            {
                // numeric versions are converted to integers when used as array keys
                $registered = array_map('strval', array_keys($this->migrations));

                return array_values(array_diff($registered, $this->applied()));
            }

            /**
             * Apply pending migrations, up to the target version (included) if provided.
             *
             * Each migration runs in a transaction, on failure its changes are
             * reverted (when supported by the database) and the migration
             * isn't marked as applied.
             *
             * @param string|null $target Last version to apply
             *
             * @return string[] Applied versions
             */
            public function migrate($target = null): array
            {
                $applied = [];

                foreach ($this->pending() as $version) {
                    if (!is_null($target) && strnatcmp($version, $target) > 0) {
                        break;
                    }

                    $this->run($version, 'up');
                    $applied[] = $version;
                }

                return $applied;
            }

            /**
             * Revert the latest applied migrations.
             *
             * @param int $steps Number of migrations to revert
             *
             * @throws \RuntimeException If a migration isn't registered or can't be reverted
             *
             * @return string[] Reverted versions
             */
            public function rollback(int $steps = 1): array
            {
                $versions = array_slice(array_reverse($this->applied()), 0, max(0, $steps));

                foreach ($versions as $version) {
                    if (!isset($this->migrations[$version])) {
                        throw new \RuntimeException(sprintf('Migration "%s" is not registered', $version));
                    }

                    if (is_null($this->migrations[$version]['down'])) {
                        throw new \RuntimeException(sprintf('Migration "%s" can\'t be reverted', $version));
                    }

                    $this->run($version, 'down');
                }

                return $versions;
            }

            /**
             * Execute a migration direction and update the tracking table.
             */
            private function run(string $version, string $direction)
            {
                $db = $this->database();

                $db->beginTransaction();

                try {
                    call_user_func($this->migrations[$version][$direction], $db);

                    if ('up' === $direction) {
                        $db->create($this->table, [
                            'version' => $version,
                            'applied_at' => time(),
                        ]);
                    } else {
                        $db->delete($this->table, $version, 'version');
                    }

                    // some databases (e.g. MySQL) implicitly commit on DDL statements
                    if ($db->inTransaction()) {
                        $db->commit();
                    }
                } catch (Throwable $exception) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }

                    throw $exception;
                }
            }

            /**
             * Create the tracking table if it doesn't exist.
             */
            private function createTable()
            {
                $this->database()->query(sprintf(
                    'CREATE TABLE IF NOT EXISTS `%s` (version VARCHAR(255) NOT NULL PRIMARY KEY, applied_at INT NOT NULL)',
                    $this->table
                ));
            }

            /**
             * Retrieve the database service, it can be replaced at runtime.
             *
             * @return object
             */
            private function database()
            {
                $database = $this->deps['database'];

                return is_callable($database)
                    ? call_user_func($database)
                    : $database;
            }
        };

        /**
         * Exposes a paginator via $deps['paginator'], building JSON responses with
         * `data`, `meta` and `links` members plus `Link` and `X-Total-Count` headers.
         *
         * @see https://www.rfc-editor.org/rfc/rfc8288 (Web Linking)
         */
        $deps['paginator'] = new class ($this) {
            /**
             * Framework instance, used to resolve other services.
             *
             * @var callable
             */
            private $mf;

            public function __construct(callable $mf)
            {
                $this->mf = $mf;
            }

            /**
             * Paginate a database table, page and size are read from the query string.
             *
             * Options:
             *   fields, where, order — passed to the database select()
             *   per_page             — default page size (default 20)
             *   max_per_page         — maximum page size requested by clients (default 100)
             *
             * @return object Response
             */
            public function paginate(string $table, array $options = [])
            {
                $options += ['fields' => '*', 'where' => null, 'order' => null];
                $database = call_user_func($this->mf, 'database');
                $page = $this->page($options);
                $rows = $database->select($table, 'COUNT(*) AS total', $options['where']);
                $total = (int) $rows[0]['total'];
                $items = $database->select(
                    $table,
                    $options['fields'],
                    $options['where'],
                    $options['order'],
                    sprintf('%d OFFSET %d', $page['per_page'], ($page['page'] - 1) * $page['per_page'])
                );

                return $this->response($items, $total, $page);
            }

            /**
             * Paginate a list of items.
             *
             * @return object Response
             */
            public function paginateArray(array $items, array $options = [])
            {
                $page = $this->page($options);
                $slice = array_slice(array_values($items), ($page['page'] - 1) * $page['per_page'], $page['per_page']);

                return $this->response($slice, count($items), $page);
            }

            /**
             * Requested page and size.
             *
             * @return array Keys: page, per_page
             */
            private function page(array $options)
            {
                $options += ['per_page' => 20, 'max_per_page' => 100];
                $request = call_user_func($this->mf, 'request');
                $page = filter_var($request->query('page', 1), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $perPage = filter_var($request->query('per_page', $options['per_page']), FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1, 'max_range' => $options['max_per_page']],
                ]);

                if (false === $page || false === $perPage) {
                    throw call_user_func($this->mf, 'errors')->create(400, sprintf(
                        'Query parameters "page" must be a positive integer and "per_page" between 1 and %d.',
                        $options['max_per_page']
                    ));
                }

                return ['page' => $page, 'per_page' => $perPage];
            }

            private function response(array $items, int $total, array $page)
            {
                $lastPage = max(1, (int) ceil($total / $page['per_page']));
                $links = [
                    'first' => $this->url(1, $page['per_page']),
                    'prev' => $page['page'] > 1 ? $this->url(min($page['page'] - 1, $lastPage), $page['per_page']) : null,
                    'next' => $page['page'] < $lastPage ? $this->url($page['page'] + 1, $page['per_page']) : null,
                    'last' => $this->url($lastPage, $page['per_page']),
                ];
                $header = [];

                foreach ($links as $rel => $url) {
                    if (!is_null($url)) {
                        $header[] = sprintf('<%s>; rel="%s"', $url, $rel);
                    }
                }

                return call_user_func($this->mf, 'response')->json([
                    'data' => $items,
                    'meta' => [
                        'page' => $page['page'],
                        'per_page' => $page['per_page'],
                        'total' => $total,
                        'last_page' => $lastPage,
                    ],
                    'links' => $links,
                ], 200, [
                    'Link' => implode(', ', $header),
                    'X-Total-Count' => (string) $total,
                ]);
            }

            private function url(int $page, int $perPage)
            {
                $request = call_user_func($this->mf, 'request');
                $query = array_merge($request->query(), ['page' => $page, 'per_page' => $perPage]);

                return $request->path().'?'.http_build_query($query);
            }
        };

        /**
         * Exposes a rate limiting middleware factory via $deps['ratelimit'],
         * counters are stored using the cache service (fixed window algorithm).
         *
         * @see https://www.rfc-editor.org/rfc/rfc6585#section-4 (429 Too Many Requests)
         */
        $deps['ratelimit'] = new class ($this) {
            /**
             * Framework instance, used to resolve other services.
             *
             * @var callable
             */
            private $mf;

            public function __construct(callable $mf)
            {
                $this->mf = $mf;
            }

            /**
             * Create the rate limiting middleware.
             *
             * Options:
             *   limit  — requests allowed in a window (default 60)
             *   window — window length in seconds (default 60)
             *   key    — `function ($request)` identifying the client (default: IP address)
             *   prefix — cache keys prefix (default "ratelimit")
             *
             * @return callable
             */
            public function middleware(array $options = [])
            {
                $options += [
                    'limit' => 60,
                    'window' => 60,
                    'key' => null,
                    'prefix' => 'ratelimit',
                ];

                return function ($request, callable $next) use ($options) {
                    $id = is_callable($options['key']) ? call_user_func($options['key'], $request) : $request->ip();

                    if (is_null($id)) {
                        return $next($request);
                    }

                    $result = $this->hit((string) $id, $options['limit'], $options['window'], $options['prefix']);
                    $headers = [
                        'X-RateLimit-Limit' => (string) $options['limit'],
                        'X-RateLimit-Remaining' => (string) $result['remaining'],
                        'X-RateLimit-Reset' => (string) $result['reset'],
                    ];

                    if (!$result['allowed']) {
                        throw call_user_func($this->mf, 'errors')->create(
                            429,
                            'Rate limit exceeded, retry later.',
                            [],
                            $headers + ['Retry-After' => (string) max(1, $result['reset'] - time())]
                        );
                    }

                    $response = $next($request);

                    foreach ($headers as $name => $value) {
                        $response = $response->withHeader($name, $value);
                    }

                    return $response;
                };
            }

            /**
             * Count a request.
             *
             * @return array Keys: allowed, remaining, reset (UNIX timestamp)
             */
            public function hit(string $id, int $limit, int $window, string $prefix = 'ratelimit')
            {
                $cache = call_user_func($this->mf, 'cache');
                $start = (int) (floor(time() / $window) * $window);
                $key = $prefix.'.'.sha1($id).'.'.$start;
                $count = (int) $cache->get($key, 0) + 1;

                $cache->set($key, $count, $window);

                return [
                    'allowed' => $count <= $limit,
                    'remaining' => max(0, $limit - $count),
                    'reset' => $start + $window,
                ];
            }
        };

        /**
         * Exposes the current HTTP request via $deps['request'].
         *
         * Data is read from superglobals and php://input, or from the state passed
         * to initialize() (used by `$mf->handle()` in tests).
         */
        $deps['request'] = new class ($this) {
            /**
             * Framework instance, used to resolve other services.
             *
             * @var callable
             */
            private $mf;

            /**
             * @var array|null
             */
            private $state;

            /**
             * Decoded body, cached.
             *
             * @var array|null
             */
            private $input;

            /**
             * Route parameters.
             *
             * @var array
             */
            private $params = [];

            /**
             * Values shared between middlewares and callbacks.
             *
             * @var array
             */
            private $attributes = [];

            public function __construct(callable $mf)
            {
                $this->mf = $mf;
            }

            /**
             * Initialize the request, superglobals are used for missing keys.
             *
             * @param array $state Keys: server, headers, query, body, post
             */
            public function initialize(array $state = [])
            {
                $server = $state['server'] ?? $_SERVER;
                $uri = $server['REQUEST_URI'] ?? '/';
                $headers = [];

                if (isset($state['headers'])) {
                    foreach ($state['headers'] as $name => $value) {
                        $headers[strtolower($name)] = implode(', ', (array) $value);
                    }
                } else {
                    foreach ($server as $key => $value) {
                        if (0 === strpos($key, 'HTTP_')) {
                            $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
                        } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'])) {
                            $headers[strtolower(str_replace('_', '-', $key))] = $value;
                        }
                    }

                    if (!isset($headers['authorization']) && isset($server['REDIRECT_HTTP_AUTHORIZATION'])) {
                        $headers['authorization'] = $server['REDIRECT_HTTP_AUTHORIZATION'];
                    }
                }

                if (isset($state['query'])) {
                    $query = $state['query'];
                } elseif (isset($state['server'])) {
                    parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);
                } else {
                    $query = $_GET;
                }

                $this->state = [
                    'server' => $server,
                    'method' => strtoupper($server['REQUEST_METHOD'] ?? 'GET'),
                    'uri' => $uri,
                    'path' => (string) parse_url($uri, PHP_URL_PATH),
                    'headers' => $headers,
                    'query' => $query,
                    'body' => array_key_exists('body', $state) ? (string) $state['body'] : null,
                    'post' => $state['post'] ?? (isset($state['server']) ? null : $_POST),
                ];
                $this->input = null;
                $this->params = [];
                $this->attributes = [];
            }

            private function state(string $key)
            {
                if (is_null($this->state)) {
                    $this->initialize();
                }

                return $this->state[$key];
            }

            public function method()
            {
                return $this->state('method');
            }

            public function uri()
            {
                return $this->state('uri');
            }

            public function path()
            {
                return $this->state('path');
            }

            public function server(string $key, $default = null)
            {
                $server = $this->state('server');

                return $server[$key] ?? $default;
            }

            /**
             * Query string parameters.
             *
             * @return mixed All the parameters if $key is null
             */
            public function query($key = null, $default = null)
            {
                return $this->lookup($this->state('query'), $key, $default);
            }

            /**
             * Header value, names are case insensitive.
             *
             * @return string|null
             */
            public function header(string $name, $default = null)
            {
                $headers = $this->state('headers');
                $name = strtolower($name);

                return $headers[$name] ?? $default;
            }

            /**
             * @return array Header values indexed by lowercase name
             */
            public function headers()
            {
                return $this->state('headers');
            }

            /**
             * Raw body.
             */
            public function body()
            {
                if (is_null($this->state('body'))) {
                    $this->state['body'] = (string) file_get_contents('php://input');
                }

                return $this->state['body'];
            }

            public function isJson()
            {
                return (bool) preg_match('#^application/([a-z0-9.+-]+\+)?json\b#i', (string) $this->header('Content-Type'));
            }

            /**
             * Body parameters, decoded from JSON or form data. Dot notation is
             * supported for nested values: `input('address.city')`.
             *
             * @throws RuntimeException A 400 error if the JSON body is malformed
             *
             * @return mixed All the parameters if $key is null
             */
            public function input($key = null, $default = null)
            {
                if (is_null($this->input)) {
                    $this->input = $this->decode();
                }

                return $this->lookup($this->input, $key, $default);
            }

            /**
             * Query string and body parameters, body parameters take precedence.
             */
            public function all()
            {
                $input = $this->input();

                return (is_array($input) ? $input : []) + $this->query();
            }

            /**
             * Validate body parameters, see the validator service.
             *
             * @return array Validated data
             */
            public function validate(array $rules, array $messages = [])
            {
                return call_user_func($this->mf, 'validator')->validate($this->all(), $rules, $messages);
            }

            /**
             * Route parameter, from named subpatterns or placeholders.
             *
             * @return mixed All the parameters if $name is null
             */
            public function param($name = null, $default = null)
            {
                return $this->lookup($this->params, $name, $default);
            }

            public function setParams(array $params)
            {
                $this->params = $params;
            }

            public function attribute(string $name, $default = null)
            {
                return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
            }

            public function setAttribute(string $name, $value)
            {
                $this->attributes[$name] = $value;

                return $this;
            }

            /**
             * Token sent using the `Authorization: Bearer <token>` header.
             *
             * @return string|null
             */
            public function bearerToken()
            {
                if (preg_match('#^Bearer\s+(\S+)$#i', trim((string) $this->header('Authorization')), $match)) {
                    return $match[1];
                }

                return null;
            }

            /**
             * Client IP address, proxies aren't trusted.
             *
             * @return string|null
             */
            public function ip()
            {
                return $this->server('REMOTE_ADDR');
            }

            /**
             * Whether the Accept header contains a media type.
             */
            public function accepts(string $type)
            {
                $accept = strtolower((string) $this->header('Accept'));

                return false !== strpos($accept, strtolower($type));
            }

            /**
             * Whether the client expects a JSON response.
             */
            public function wantsJson()
            {
                return $this->isJson() || (bool) preg_match('#[/+]json\b#', strtolower((string) $this->header('Accept')));
            }

            private function decode()
            {
                if ($this->isJson()) {
                    $body = trim($this->body());

                    if ('' === $body) {
                        return [];
                    }

                    $data = json_decode($body, true);

                    if (JSON_ERROR_NONE !== json_last_error()) {
                        throw call_user_func($this->mf, 'errors')->create(400, 'Malformed JSON body: '.json_last_error_msg());
                    }

                    return $data;
                }

                $post = $this->state('post');

                if (!is_null($post) && ('POST' === $this->method() || !empty($post))) {
                    return $post;
                }

                // form data sent with methods other than POST isn't parsed by PHP
                parse_str($this->body(), $data);

                return $data;
            }

            private function lookup($data, $key, $default)
            {
                if (is_null($key)) {
                    return $data;
                }

                if (is_array($data) && array_key_exists($key, $data)) {
                    return $data[$key];
                }

                foreach (explode('.', $key) as $segment) {
                    if (!is_array($data) || !array_key_exists($segment, $data)) {
                        return $default;
                    }

                    $data = $data[$segment];
                }

                return $data;
            }
        };

        /**
         * Exposes a response factory via $deps['response'].
         *
         * Responses are immutable objects using PSR-7 method names
         * (getStatusCode, withStatus, getHeaders, withHeader, getBody, withBody...),
         * route callbacks can return them instead of printing the output.
         *
         * @see https://www.php-fig.org/psr/psr-7/
         * @see https://www.rfc-editor.org/rfc/rfc9457 (Problem Details)
         */
        $deps['response'] = new class () {
            /**
             * Reason phrases of the most common status codes.
             *
             * @var array
             */
            private $phrases = [
                200 => 'OK',
                201 => 'Created',
                202 => 'Accepted',
                204 => 'No Content',
                301 => 'Moved Permanently',
                302 => 'Found',
                303 => 'See Other',
                304 => 'Not Modified',
                307 => 'Temporary Redirect',
                308 => 'Permanent Redirect',
                400 => 'Bad Request',
                401 => 'Unauthorized',
                403 => 'Forbidden',
                404 => 'Not Found',
                405 => 'Method Not Allowed',
                406 => 'Not Acceptable',
                409 => 'Conflict',
                410 => 'Gone',
                412 => 'Precondition Failed',
                413 => 'Payload Too Large',
                415 => 'Unsupported Media Type',
                422 => 'Unprocessable Content',
                429 => 'Too Many Requests',
                500 => 'Internal Server Error',
                501 => 'Not Implemented',
                502 => 'Bad Gateway',
                503 => 'Service Unavailable',
                504 => 'Gateway Timeout',
            ];

            /**
             * Create a response.
             *
             * @param string $body    Response body
             * @param int    $status  HTTP status code
             * @param array  $headers Header values indexed by name
             *
             * @return object
             */
            public function create(string $body = '', int $status = 200, array $headers = [])
            {
                return new class ($body, $status, $headers) {
                    private $body;
                    private $status;
                    private $headers = [];

                    public function __construct(string $body, int $status, array $headers)
                    {
                        $this->body = $body;
                        $this->status = $status;

                        foreach ($headers as $name => $value) {
                            $this->headers[strtolower($name)] = [$name, array_values((array) $value)];
                        }
                    }

                    public function getStatusCode()
                    {
                        return $this->status;
                    }

                    public function withStatus(int $status)
                    {
                        $clone = clone $this;
                        $clone->status = $status;

                        return $clone;
                    }

                    /**
                     * @return array Header values indexed by name
                     */
                    public function getHeaders()
                    {
                        $headers = [];

                        foreach ($this->headers as $header) {
                            $headers[$header[0]] = $header[1];
                        }

                        return $headers;
                    }

                    public function hasHeader(string $name)
                    {
                        return isset($this->headers[strtolower($name)]);
                    }

                    /**
                     * @return string[]
                     */
                    public function getHeader(string $name)
                    {
                        return $this->hasHeader($name) ? $this->headers[strtolower($name)][1] : [];
                    }

                    public function getHeaderLine(string $name)
                    {
                        return implode(', ', $this->getHeader($name));
                    }

                    /**
                     * @param string|string[] $value
                     */
                    public function withHeader(string $name, $value)
                    {
                        $clone = clone $this;
                        $clone->headers[strtolower($name)] = [$name, array_values((array) $value)];

                        return $clone;
                    }

                    /**
                     * @param string|string[] $value
                     */
                    public function withAddedHeader(string $name, $value)
                    {
                        return $this->withHeader($name, array_merge($this->getHeader($name), (array) $value));
                    }

                    public function withoutHeader(string $name)
                    {
                        $clone = clone $this;
                        unset($clone->headers[strtolower($name)]);

                        return $clone;
                    }

                    public function getBody()
                    {
                        return $this->body;
                    }

                    public function withBody(string $body)
                    {
                        $clone = clone $this;
                        $clone->body = $body;

                        return $clone;
                    }

                    /**
                     * Set the Cache-Control header.
                     *
                     * @param int  $maxAge Seconds the response can be cached, 0 disables the cache
                     * @param bool $public Whether shared caches can store the response
                     */
                    public function withCache(int $maxAge, bool $public = true)
                    {
                        if ($maxAge <= 0) {
                            return $this->withHeader('Cache-Control', 'no-store');
                        }

                        return $this->withHeader('Cache-Control', sprintf('%s, max-age=%d', $public ? 'public' : 'private', $maxAge));
                    }

                    public function withEtag(string $etag, bool $weak = false)
                    {
                        return $this->withHeader('ETag', ($weak ? 'W/' : '').'"'.trim($etag, '"').'"');
                    }

                    /**
                     * @param int|DateTimeInterface $time
                     */
                    public function withLastModified($time)
                    {
                        $timestamp = $time instanceof DateTimeInterface ? $time->getTimestamp() : (int) $time;

                        return $this->withHeader('Last-Modified', gmdate('D, d M Y H:i:s', $timestamp).' GMT');
                    }

                    /**
                     * Decoded JSON body, useful in tests.
                     *
                     * @return mixed
                     */
                    public function json()
                    {
                        return json_decode($this->body, true);
                    }

                    public function __toString()
                    {
                        return $this->body;
                    }
                };
            }

            /**
             * Create a JSON response.
             *
             * @param mixed $data    Value encoded as JSON
             * @param int   $status  HTTP status code
             * @param array $headers Additional headers
             * @param int   $flags   json_encode() flags
             *
             * @throws \InvalidArgumentException If the value can't be encoded
             *
             * @return object
             */
            public function json($data, int $status = 200, array $headers = [], int $flags = 0)
            {
                $body = json_encode($data, $flags | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                if (false === $body || JSON_ERROR_NONE !== json_last_error()) {
                    throw new \InvalidArgumentException('Unable to encode JSON: '.json_last_error_msg());
                }

                return $this->create($body, $status, $headers + ['Content-Type' => 'application/json']);
            }

            public function text(string $text, int $status = 200, array $headers = [])
            {
                return $this->create($text, $status, $headers + ['Content-Type' => 'text/plain; charset=UTF-8']);
            }

            public function html(string $html, int $status = 200, array $headers = [])
            {
                return $this->create($html, $status, $headers + ['Content-Type' => 'text/html; charset=UTF-8']);
            }

            public function noContent(array $headers = [])
            {
                return $this->create('', 204, $headers);
            }

            public function redirect(string $url, int $status = 302, array $headers = [])
            {
                return $this->create('', $status, $headers + ['Location' => $url]);
            }

            /**
             * Create a 201 Created response.
             *
             * @param string $location URL of the created resource
             * @param mixed  $data     Value encoded as JSON, null for an empty body
             */
            public function created(string $location, $data = null, array $headers = [])
            {
                $headers += ['Location' => $location];

                return is_null($data) ? $this->create('', 201, $headers) : $this->json($data, 201, $headers);
            }

            /**
             * Create a Problem Details response (RFC 9457).
             *
             * @param int         $status     HTTP status code
             * @param string|null $detail     Explanation specific to this occurrence of the problem
             * @param array       $extensions Additional members (type, title, instance, errors...)
             * @param array       $headers    Additional headers
             */
            public function problem(int $status, $detail = null, array $extensions = [], array $headers = [])
            {
                $problem = [
                    'type' => 'about:blank',
                    'title' => $this->reasonPhrase($status),
                    'status' => $status,
                ];

                if (!is_null($detail) && '' !== $detail) {
                    $problem['detail'] = $detail;
                }

                $problem = array_merge($problem, $extensions, ['status' => $status]);

                return $this->json($problem, $status, $headers + ['Content-Type' => 'application/problem+json']);
            }

            public function reasonPhrase(int $status)
            {
                return $this->phrases[$status] ?? 'Unknown Status';
            }

            /**
             * Whether a value can be sent as a response, PSR-7 responses included.
             */
            public function isResponse($value)
            {
                return is_object($value)
                    && method_exists($value, 'getStatusCode')
                    && method_exists($value, 'getHeaders')
                    && method_exists($value, 'getBody')
                    && method_exists($value, 'withBody');
            }

            /**
             * Middleware adding an ETag to successful GET/HEAD responses and replying
             * with 304 Not Modified when If-None-Match or If-Modified-Since match.
             *
             * @return callable
             */
            public function conditional(bool $weak = false)
            {
                return function ($request, callable $next) use ($weak) {
                    $response = $next($request);

                    if (!in_array($request->method(), ['GET', 'HEAD']) || 200 !== $response->getStatusCode()) {
                        return $response;
                    }

                    if (!$response->hasHeader('ETag')) {
                        $response = $response->withEtag(md5((string) $response->getBody()), $weak);
                    }

                    $etag = $response->getHeaderLine('ETag');
                    $ifNoneMatch = $request->header('If-None-Match');
                    $notModified = false;

                    if (!is_null($ifNoneMatch)) {
                        // weak comparison, as required for If-None-Match
                        $normalize = function ($tag) {
                            return preg_replace('#^W/#', '', trim($tag));
                        };
                        $tags = array_map($normalize, explode(',', $ifNoneMatch));
                        $notModified = in_array('*', $tags) || in_array($normalize($etag), $tags);
                    } elseif (!is_null($since = $request->header('If-Modified-Since')) && $response->hasHeader('Last-Modified')) {
                        $notModified = strtotime($response->getHeaderLine('Last-Modified')) <= strtotime($since);
                    }

                    if (!$notModified) {
                        return $response;
                    }

                    $headers = [];

                    foreach (['Cache-Control', 'Content-Location', 'Date', 'ETag', 'Expires', 'Last-Modified', 'Vary'] as $name) {
                        if ($response->hasHeader($name)) {
                            $headers[$name] = $response->getHeader($name);
                        }
                    }

                    return $this->create('', 304, $headers);
                };
            }
        };

        unset($deps['template:escape']);

        $deps['template'] = new class () {
            /**
             * Layout requested by the template currently rendered.
             *
             * @var array|null
             */
            private $layout;

            /**
             * Rendered sections, shared between a template and its layouts.
             *
             * @var array
             */
            private $sections = [];

            /**
             * Names of the sections opened and not closed yet.
             *
             * @var array
             */
            private $sectionsStack = [];

            /**
             * Nesting level of render calls, used to reset sections between renders.
             *
             * @var int
             */
            private $depth = 0;

            /**
             * Simple template engine to manipulate and render .php files.
             *
             * @return string
             */
            public function render(string $template, array $data = [])
            {
                if (false === stream_resolve_include_path($template)) {
                    throw new InvalidArgumentException(sprintf('Template "%s" not found.', $template));
                }

                if (0 === $this->depth) {
                    $this->sections = [];
                }

                $obLevel = ob_get_level();
                $stackSize = count($this->sectionsStack);
                $previousLayout = $this->layout;
                $this->layout = null;
                ++$this->depth;

                try {
                    ob_start();

                    // the closure isolates the scope, $template and $data can't be overwritten
                    $load = function () {
                        extract(func_get_arg(1), EXTR_SKIP);

                        require func_get_arg(0);
                    };
                    $load($template, array_diff_key($data, ['this' => null]));

                    if (count($this->sectionsStack) > $stackSize) {
                        throw new LogicException(sprintf('Section "%s" not closed.', end($this->sectionsStack)));
                    }

                    $content = ob_get_clean();
                    $layout = $this->layout;

                    if (null !== $layout) {
                        $this->sections['content'] = $content;
                        $content = $this->render($layout[0], array_merge($data, $layout[1]));
                    }

                    return $content;
                } finally {
                    // buffers left open by a failure (or by unclosed sections) are discarded
                    while (ob_get_level() > $obLevel) {
                        ob_end_clean();
                    }

                    $this->sectionsStack = array_slice($this->sectionsStack, 0, $stackSize);
                    $this->layout = $previousLayout;
                    --$this->depth;

                    if (0 === $this->depth) {
                        $this->sections = [];
                    }
                }
            }

            /**
             * Wrap the current template into a layout, the output of the template
             * will be available in the layout as the "content" section.
             *
             * @param string $template
             * @param array  $data     additional data merged into the template data
             */
            public function layout(string $template, array $data = [])
            {
                $this->layout = [$template, $data];
            }

            /**
             * Start capturing the output of a section.
             *
             * @param string $name
             */
            public function start(string $name)
            {
                if ('content' === $name) {
                    throw new LogicException('Section name "content" is reserved.');
                }

                $this->sectionsStack[] = $name;

                ob_start();
            }

            /**
             * Stop capturing the output of the last section started.
             * Sections defined by a template have precedence over the ones defined
             * by its layouts, so layouts can define defaults.
             */
            public function stop()
            {
                if (empty($this->sectionsStack)) {
                    throw new LogicException('No section started.');
                }

                $name = array_pop($this->sectionsStack);
                $content = ob_get_clean();

                if (!isset($this->sections[$name])) {
                    $this->sections[$name] = $content;
                }
            }

            /**
             * Get the content of a section.
             *
             * @param string $name
             * @param string $default returned when the section isn't defined
             *
             * @return string
             */
            public function section(string $name, string $default = '')
            {
                return $this->sections[$name] ?? $default;
            }

            /**
             * Escape values to be rendered safely in templates.
             *
             * @param mixed $value
             *
             * @return string
             */
            public function e($value)
            {
                if (is_array($value) || (is_object($value) && !method_exists($value, '__toString'))) {
                    throw new InvalidArgumentException(sprintf('Value of type "%s" can\'t be escaped.', gettype($value)));
                }

                return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        };

        /**
         * Exposes an input validator via $deps['validator'].
         *
         * Rules are defined as a pipe separated string or as a list, custom rules
         * are callables returning true or an error message:
         *
         *   [
         *       'email' => 'required|email',
         *       'age' => ['nullable', 'integer', 'min:18'],
         *       'address.city' => 'required|string|max:64',
         *       'code' => ['required', function ($value, $field, $data) { return ... ?: 'Invalid code.'; }],
         *   ]
         *
         * Available rules: required, nullable, string, integer, numeric, boolean,
         * array, email, url, uuid, date, min:n, max:n, between:min,max, in:a,b,c,
         * regex:pattern, confirmed.
         */
        $deps['validator'] = new class ($this) {
            /**
             * Framework instance, used to resolve other services.
             *
             * @var callable
             */
            private $mf;

            /**
             * Default error messages.
             *
             * @var array
             */
            private $messages = [
                'required' => 'The {field} field is required.',
                'string' => 'The {field} field must be a string.',
                'integer' => 'The {field} field must be an integer.',
                'numeric' => 'The {field} field must be a number.',
                'boolean' => 'The {field} field must be true or false.',
                'array' => 'The {field} field must be an array.',
                'email' => 'The {field} field must be a valid email address.',
                'url' => 'The {field} field must be a valid URL.',
                'uuid' => 'The {field} field must be a valid UUID.',
                'date' => 'The {field} field must be a valid date.',
                'min' => 'The {field} field must be at least {0}.',
                'max' => 'The {field} field must not be greater than {0}.',
                'between' => 'The {field} field must be between {0} and {1}.',
                'in' => 'The selected {field} is invalid.',
                'regex' => 'The {field} field format is invalid.',
                'confirmed' => 'The {field} field confirmation does not match.',
            ];

            public function __construct(callable $mf)
            {
                $this->mf = $mf;
            }

            /**
             * Validate data and return the validated fields.
             *
             * @param array $data     Data to validate
             * @param array $rules    Rules indexed by field name, dot notation allowed
             * @param array $messages Custom messages indexed by `field.rule` or `rule`
             *
             * @throws RuntimeException A 422 error with the `errors` member if validation fails
             *
             * @return array
             */
            public function validate(array $data, array $rules, array $messages = [])
            {
                $errors = $this->errors($data, $rules, $messages);

                if (!empty($errors)) {
                    throw call_user_func($this->mf, 'errors')->create(422, 'The given data was invalid.', ['errors' => $errors]);
                }

                $validated = [];

                foreach (array_keys($rules) as $field) {
                    if ($this->has($data, $field)) {
                        $this->set($validated, $field, $this->get($data, $field));
                    }
                }

                return $validated;
            }

            /**
             * Validate data and return error messages indexed by field name.
             *
             * @return array
             */
            public function errors(array $data, array $rules, array $messages = [])
            {
                $errors = [];

                foreach ($rules as $field => $fieldRules) {
                    $fieldRules = is_string($fieldRules) ? explode('|', $fieldRules) : (array) $fieldRules;
                    $present = $this->has($data, $field);
                    $value = $this->get($data, $field);
                    $empty = !$present || is_null($value) || '' === $value || [] === $value;

                    if ($empty) {
                        if (in_array('required', $fieldRules, true)) {
                            $errors[$field][] = $this->message('required', $field, [], $messages);
                        }

                        continue;
                    }

                    $type = $this->type($fieldRules);

                    foreach ($fieldRules as $rule) {
                        if (is_callable($rule) && !is_string($rule)) {
                            $result = call_user_func($rule, $value, $field, $data);

                            if (true !== $result) {
                                $errors[$field][] = is_string($result) ? $result : $this->message('regex', $field, [], $messages);
                            }

                            continue;
                        }

                        $parts = explode(':', $rule, 2);
                        $name = $parts[0];
                        $args = isset($parts[1]) ? ('regex' === $name ? [$parts[1]] : explode(',', $parts[1])) : [];

                        if (in_array($name, ['required', 'nullable'], true)) {
                            continue;
                        }

                        if (!isset($this->messages[$name])) {
                            throw new InvalidArgumentException(sprintf('Unknown validation rule "%s"', $name));
                        }

                        if (!$this->check($name, $value, $args, $type, $data, $field)) {
                            $errors[$field][] = $this->message($name, $field, $args, $messages);
                        }
                    }
                }

                return $errors;
            }

            private function check(string $rule, $value, array $args, string $type, array $data, string $field)
            {
                switch ($rule) {
                    case 'string':
                        return is_string($value);

                    case 'integer':
                        return false !== filter_var($value, FILTER_VALIDATE_INT);

                    case 'numeric':
                        return is_numeric($value);

                    case 'boolean':
                        return in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true);

                    case 'array':
                        return is_array($value);

                    case 'email':
                        return is_string($value) && false !== filter_var($value, FILTER_VALIDATE_EMAIL);

                    case 'url':
                        return is_string($value) && false !== filter_var($value, FILTER_VALIDATE_URL);

                    case 'uuid':
                        return is_string($value) && preg_match('#^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$#i', $value);

                    case 'date':
                        return is_string($value) && false !== strtotime($value);

                    case 'min':
                        return $this->size($value, $type) >= $args[0];

                    case 'max':
                        return $this->size($value, $type) <= $args[0];

                    case 'between':
                        $size = $this->size($value, $type);

                        return $size >= $args[0] && $size <= $args[1];

                    case 'in':
                        return is_scalar($value) && in_array((string) $value, $args, true);

                    case 'regex':
                        return is_scalar($value) && preg_match($args[0], (string) $value);

                    case 'confirmed':
                        return $this->has($data, $field.'_confirmation') && $this->get($data, $field.'_confirmation') === $value;
                }
            }

            /**
             * Type used to compare sizes.
             */
            private function type(array $rules)
            {
                foreach ($rules as $rule) {
                    if (in_array($rule, ['integer', 'numeric'], true)) {
                        return 'numeric';
                    }

                    if ('array' === $rule) {
                        return 'array';
                    }
                }

                return 'string';
            }

            private function size($value, string $type)
            {
                if (is_array($value)) {
                    return count($value);
                }

                if ('numeric' === $type && is_numeric($value)) {
                    return $value + 0;
                }

                $value = (string) $value;

                return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
            }

            private function message(string $rule, string $field, array $args, array $messages)
            {
                if (isset($messages[$field.'.'.$rule])) {
                    $message = $messages[$field.'.'.$rule];
                } elseif (isset($messages[$rule])) {
                    $message = $messages[$rule];
                } else {
                    $message = $this->messages[$rule];
                }

                $replace = ['{field}' => str_replace('_', ' ', $field)];

                foreach ($args as $i => $arg) {
                    $replace['{'.$i.'}'] = $arg;
                }

                return strtr($message, $replace);
            }

            private function has(array $data, string $field)
            {
                if (array_key_exists($field, $data)) {
                    return true;
                }

                foreach (explode('.', $field) as $segment) {
                    if (!is_array($data) || !array_key_exists($segment, $data)) {
                        return false;
                    }

                    $data = $data[$segment];
                }

                return true;
            }

            private function get(array $data, string $field)
            {
                if (array_key_exists($field, $data)) {
                    return $data[$field];
                }

                foreach (explode('.', $field) as $segment) {
                    if (!is_array($data) || !array_key_exists($segment, $data)) {
                        return null;
                    }

                    $data = $data[$segment];
                }

                return $data;
            }

            private function set(array &$data, string $field, $value)
            {
                $keys = explode('.', $field);

                while (count($keys) > 1) {
                    $key = array_shift($keys);

                    if (!isset($data[$key]) || !is_array($data[$key])) {
                        $data[$key] = [];
                    }

                    $data = &$data[$key];
                }

                $data[array_shift($keys)] = $value;
            }
        };
    }

    public function __invoke()
    {
        // local references used to keep the function body unchanged
        $deploy = &$this->deploy;
        $deps = &$this->deps;

        // used to shorten code
        $args = func_get_args();

        // used to retrieve currently defined matches
        // http://www.php.net/manual/en/regexp.reference.conditional.php
        // http://stackoverflow.com/questions/14598972/catch-all-regular-expression
        switch (func_num_args()) {
            case 1:
                // Set of utilities
                switch ($args[0]) {
                    case 'router:not-found':
                        if (!$this->isCli() || $this->testing) {
                            $pattern = '(?!('.implode('|', $this->matches).')$).*';
                            $this->notFound[$pattern] = true;

                            return $pattern;
                        }

                        break;

                    case 'router:method-not-allowed':
                        return static::METHOD_NOT_ALLOWED;

                    case 'router:allowed-methods':
                        $request = $this->service('request');

                        return $this->allowedMethods($request->path(), $this->basePath($request));
                }

                // using $GLOBALS as a container, variable names must match
                // this regular expression
                // http://www.php.net/manual/en/language.variables.basics.php
                if (is_scalar($args[0]) && preg_match('#^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*#', $args[0])) {
                    if (!empty($deps['container']) && is_object($deps['container'])) {
                        // if there's a container, use it
                        return $deps['container']->get($args[0]);
                    }

                    return is_callable($deps[$args[0]])
                        ? call_user_func($deps[$args[0]])
                        : $deps[$args[0]];
                }

                break;

            case 2:
                // using $GLOBALS as a container, variable names must match
                // this regular expression
                // http://www.php.net/manual/en/language.variables.basics.php
                if (is_scalar($args[0]) && preg_match('#^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*#', $args[0])) {
                    // functions used for Dependency Injection and settings
                    if (!empty($deps['container']) && is_object($deps['container'])) {
                        // if there's a container, use it
                        $deps['container']->set($args[0], $args[1]);

                        return;
                    }

                    return $deps[$args[0]] = $args[1];
                }

                break;
        }

        // a pattern followed by a callback is a route, even in command line mode,
        // so routes can be listed and tested without a webserver
        $isRoute = isset($args[0], $args[1]) && is_string($args[0]) && is_callable($args[1]);

        if ($isRoute && (!$this->isCli() || !is_callable($args[0]))) {
            return $this->route(
                $args[2] ?? 'GET',
                $args[0],
                $args[1],
                ['priority' => $args[3] ?? 0]
            );
        }

        if (is_null($deploy)) {
            /**
             * Command line interface for the main function.
             *
             * @param callback $cb       Function invoked when script ends
             * @param int      $priority Set `$cb` priority from 0 (high) to ~1.8e308 (low)
             *
             * @link http://php.net/manual/en/language.types.float.php
             *
             * @return void
             */
            $deploy = function ($cb, $priority = 0) use (&$deploy) {
                // Checking well formed call
                assert(is_callable($cb));
                assert(is_numeric($priority));

                if ($priority > 0) {
                    // Recursion is used to set callback priority
                    return register_shutdown_function($deploy, $cb, $priority - 1);
                }

                /**
                 * Arguments passed to the script.
                 *
                 * @link http://php.net/manual/en/reserved.variables.argv.php
                 *
                 * @var array
                 */
                $argv = $GLOBALS['argv'];

                $argv[0] = $cb;

                // register_shutdown_function is used to call added functions when script ends
                // http://it2.php.net/manual/en/function.register-shutdown-function.php
                return call_user_func_array('register_shutdown_function', array_values($argv));
            };
        }

        return call_user_func_array($deploy, $args);
    }

    /**
     * Define a route.
     *
     * Options:
     *   priority   — from 0 (high) to ~1.8e308 (low), default 0
     *   middleware — list of callables `function ($request, callable $next)`
     *   name       — route name, used as OpenAPI operationId
     *   openapi    — OpenAPI operation object merged in `openapi()`
     *
     * @param string|string[] $methods Request methods, a regular expression or a list
     * @param string          $pattern Regular expression or `{name}`/`{name:regex}` placeholders
     * @param callable        $cb      Function invoked when there's a match
     * @param array           $options Route options
     *
     * @return $this
     */
    public function route($methods, string $pattern, callable $cb, array $options = [])
    {
        $middleware = isset($options['middleware']) ? (array) $options['middleware'] : [];
        $openapi = $options['openapi'] ?? [];
        $prefix = '';

        foreach ($this->groups as $group) {
            $prefix .= $group['prefix'];
            $middleware = array_merge($group['middleware'], $middleware);
            $openapi = array_merge_recursive($group['openapi'], $openapi);
        }

        $special = static::METHOD_NOT_ALLOWED === $pattern || isset($this->notFound[$pattern]);
        $path = $special ? $pattern : $prefix.$pattern;
        $regex = $special ? $pattern : $this->compile($path);

        if (is_array($methods)) {
            $list = array_values(array_unique(array_map('strtoupper', $methods)));
            $methods = '^('.implode('|', array_map('preg_quote', $list)).')$';
        }

        if (!$special) {
            // match stored as unique using the Adler-32 algorithm that is faster than md5
            // http://en.wikipedia.org/wiki/Adler-32
            // http://3v4l.org/7MC3j
            $this->matches[hash('adler32', $regex)] = $regex;
        }

        $this->routes[] = [
            'methods' => $methods,
            'list' => $list ?? null,
            'path' => $path,
            'regex' => $regex,
            'callback' => $cb,
            'priority' => $options['priority'] ?? 0,
            'middleware' => $middleware,
            'name' => $options['name'] ?? null,
            'openapi' => $openapi,
            'special' => $special,
        ];

        $this->register();

        return $this;
    }

    public function get(string $pattern, callable $cb, array $options = [])
    {
        return $this->route(['GET', 'HEAD'], $pattern, $cb, $options);
    }

    public function post(string $pattern, callable $cb, array $options = [])
    {
        return $this->route(['POST'], $pattern, $cb, $options);
    }

    public function put(string $pattern, callable $cb, array $options = [])
    {
        return $this->route(['PUT'], $pattern, $cb, $options);
    }

    public function patch(string $pattern, callable $cb, array $options = [])
    {
        return $this->route(['PATCH'], $pattern, $cb, $options);
    }

    public function delete(string $pattern, callable $cb, array $options = [])
    {
        return $this->route(['DELETE'], $pattern, $cb, $options);
    }

    public function options(string $pattern, callable $cb, array $options = [])
    {
        return $this->route(['OPTIONS'], $pattern, $cb, $options);
    }

    public function any(string $pattern, callable $cb, array $options = [])
    {
        return $this->route('.*', $pattern, $cb, $options);
    }

    /**
     * Define routes sharing a prefix, middlewares and OpenAPI options.
     *
     * @param string   $prefix  Prefix prepended to every route pattern
     * @param callable $routes  Function receiving the framework instance
     * @param array    $options `middleware` and `openapi` options
     *
     * @return $this
     */
    public function group(string $prefix, callable $routes, array $options = [])
    {
        $this->groups[] = [
            'prefix' => $prefix,
            'middleware' => isset($options['middleware']) ? (array) $options['middleware'] : [],
            'openapi' => $options['openapi'] ?? [],
        ];

        try {
            call_user_func($routes, $this);
        } finally {
            array_pop($this->groups);
        }

        return $this;
    }

    /**
     * Add a middleware wrapping every request, including the not matched ones.
     *
     * @param callable $middleware Function `function ($request, callable $next)`
     *
     * @return $this
     */
    public function middleware(callable $middleware)
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    /**
     * Toggle the testing mode: routes are dispatched only calling `handle()`.
     *
     * @return $this
     */
    public function testing(bool $enabled = true)
    {
        $this->testing = $enabled;

        return $this;
    }

    /**
     * Dispatch a request in-process and return the response without sending it.
     *
     * Options: headers, query, body, json, form, server.
     *
     * @return object Response
     */
    public function handle(string $method, string $uri, array $options = [])
    {
        $request = $this->service('request');
        $server = ($options['server'] ?? []) + [
            'REQUEST_METHOD' => strtoupper($method),
            'REQUEST_URI' => $uri,
            'SCRIPT_NAME' => '/index.php',
        ];
        $headers = $options['headers'] ?? [];
        $body = $options['body'] ?? '';

        if (isset($options['json'])) {
            $body = json_encode($options['json']);
            $headers += ['Content-Type' => 'application/json'];
        }

        if (isset($options['form'])) {
            $body = http_build_query($options['form']);
            $headers += ['Content-Type' => 'application/x-www-form-urlencoded'];
        }

        $request->initialize([
            'server' => $server,
            'headers' => $headers,
            'query' => $options['query'] ?? null,
            'body' => $body,
        ]);

        $response = $this->process($request);

        // the next request will be built from globals again
        $request->initialize();

        return $response;
    }

    /**
     * List defined routes.
     *
     * @return array
     */
    public function routes()
    {
        $routes = [];

        foreach ($this->routes as $route) {
            if ($route['special']) {
                continue;
            }

            $routes[] = [
                'methods' => $this->routeMethods($route),
                'path' => $route['path'],
                'name' => $route['name'],
                'priority' => $route['priority'],
            ];
        }

        return $routes;
    }

    /**
     * Generate an OpenAPI 3 document from the defined routes.
     *
     * Routes that can't be represented as an OpenAPI path are skipped.
     *
     * @param array $info  OpenAPI info object, `title` and `version` are required
     * @param array $extra Root keys merged into the document (servers, components...)
     *
     * @return array
     */
    public function openapi(array $info = [], array $extra = [])
    {
        $paths = [];

        foreach ($this->routes as $route) {
            if ($route['special'] || false === ($path = $this->openapiPath($route['path'], $parameters))) {
                continue;
            }

            $methods = array_diff($this->routeMethods($route), is_null($route['list']) ? ['HEAD', 'OPTIONS'] : ['HEAD']);

            foreach ($methods as $method) {
                $operation = $route['openapi'];

                if (!empty($route['name']) && !isset($operation['operationId'])) {
                    $operation['operationId'] = $route['name'];
                }

                if (!empty($parameters)) {
                    $operation['parameters'] = array_merge(
                        $parameters,
                        $operation['parameters'] ?? []
                    );
                }

                if (empty($operation['responses'])) {
                    $operation['responses'] = ['200' => ['description' => 'OK']];
                }

                $paths[$path][strtolower($method)] = $operation;
            }
        }

        return array_merge([
            'openapi' => '3.0.3',
            'info' => $info + ['title' => 'API', 'version' => '1.0.0'],
            'paths' => empty($paths) ? new stdClass() : $paths,
        ], $extra);
    }

    /**
     * Resolve a dependency.
     */
    private function service(string $id)
    {
        return $this->__invoke($id);
    }

    private function isCli()
    {
        if (is_null($this->cli)) {
            $this->cli = empty($_SERVER['REQUEST_URI']);
        }

        return $this->cli;
    }

    /**
     * Register once the function dispatching routes when the script ends.
     */
    private function register()
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;

        register_shutdown_function(function () {
            if ($this->testing) {
                return;
            }

            if ($this->isCli()) {
                $this->console();

                return;
            }

            $request = $this->service('request');
            $response = $this->process($request);

            if (!headers_sent()) {
                http_response_code($response->getStatusCode());

                foreach ($response->getHeaders() as $name => $values) {
                    foreach ((array) $values as $i => $value) {
                        header($name.': '.$value, 0 === $i);
                    }
                }
            }

            echo (string) $response->getBody();
        });
    }

    /**
     * Built-in commands available in command line mode.
     */
    private function console()
    {
        $command = $GLOBALS['argv'][1] ?? null;

        if ('crystal:routes' === $command) {
            foreach ($this->routes() as $route) {
                echo rtrim(sprintf('%-24s %-40s %s', implode('|', $route['methods']), $route['path'], $route['name'])), "\n";
            }
        }

        if ('crystal:openapi' === $command) {
            echo json_encode($this->openapi(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
        }
    }

    /**
     * Compile `{name}` and `{name:regex}` placeholders to named subpatterns.
     */
    private function compile(string $pattern)
    {
        return preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::((?:[^{}]++|\{[^{}]*\})+))?\}#',
            function ($match) {
                return sprintf('(?P<%s>%s)', $match[1], $match[2] ?? '[^/]+');
            },
            $pattern
        );
    }

    private function basePath($request)
    {
        // base path for each route
        return quotemeta(rtrim(dirname($request->server('SCRIPT_NAME', '/')), '/\\'));
    }

    /**
     * Dispatch the current request through middlewares and routes.
     *
     * @return object Response
     */
    private function process($request)
    {
        $core = function ($request) {
            $method = $request->method();
            $path = $request->path();
            $base = $this->basePath($request);
            $responses = [];
            $matched = false;
            $done = 0;

            // routes defined while dispatching are dispatched too
            while ($done < count($this->routes)) {
                $queue = [];

                foreach (array_slice($this->routes, $done, null, true) as $index => $route) {
                    // differenced FIFO queues for each priority
                    $queue[] = [$route['priority'] > 0 ? ceil($route['priority']) : 0, $index, $route];
                }

                $done = count($this->routes);

                usort($queue, function ($a, $b) {
                    return $a[0] == $b[0] ? $a[1] - $b[1] : ($a[0] < $b[0] ? -1 : 1);
                });

                foreach ($queue as $item) {
                    $route = $item[2];

                    if (static::METHOD_NOT_ALLOWED === $route['regex']) {
                        if ($this->hasMatch($method, $path, $base) || empty($allowed = $this->allowedMethods($path, $base))) {
                            continue;
                        }

                        $matched = true;
                        $response = $this->invoke($route, [], $request);

                        if (200 === $response->getStatusCode()) {
                            $response = $response->withStatus(405);
                        }

                        $responses[] = $response->withHeader('Allow', implode(', ', $allowed));

                        continue;
                    }

                    if (false === ($args = $this->match($route, $method, $path, $base))) {
                        continue;
                    }

                    $matched = true;
                    $responses[] = $this->invoke($route, $args, $request);
                }
            }

            if (!$matched) {
                return $this->fallback($request, $method, $path, $base);
            }

            return $this->merge($responses);
        };

        try {
            $response = $this->pipeline($this->middlewares, $core, $request);
        } catch (Throwable $exception) {
            $response = $this->service('errors')->render($exception, $request);
        }

        if ('HEAD' === $request->method()) {
            $response = $response->withBody('');
        }

        return $response;
    }

    /**
     * Arguments passed to the route callback, false if the route doesn't match.
     *
     * @return array|false
     */
    private function match(array $route, string $method, string $path, string $base)
    {
        if (
            !preg_match('#'.$route['methods'].'#', $method) ||
            !preg_match('#^'.$base.$route['regex'].'$#', $path, $submatches)
        ) {
            return false;
        }

        // Named subpatterns are allowed
        // http://it2.php.net/manual/en/regexp.reference.subpatterns.php
        $named = array_filter($submatches, 'is_string', ARRAY_FILTER_USE_KEY);
        $submatches = array_unique($submatches);
        // If matches is provided, then it is filled with the results of search.
        // $submatches[0] will contain the text that matched the full pattern,
        // $submatches[1] will have the text that matched the first captured parenthesized
        // subpattern, and so on.
        unset($submatches[0]);

        // Snippet used to extract parameter from a callable object.
        $reflector = $this->reflect($route['callback']);
        $params = [];

        foreach ($reflector->getParameters() as $parameter) {
            // reset to prevent key value
            $params[$parameter->name] = null;
        }

        // user can use named parameters only if explicitly requested
        if (array_intersect(array_keys($params), array_keys($submatches))) {
            $submatches = array_merge($params, $submatches);
        }

        return ['named' => $named, 'arguments' => array_values($submatches)];
    }

    private function reflect(callable $cb)
    {
        if ($cb instanceof Closure || (is_string($cb) && false === strpos($cb, '::'))) {
            return new ReflectionFunction($cb);
        }

        if (is_array($cb)) {
            return new ReflectionMethod($cb[0], $cb[1]);
        }

        if (is_object($cb)) {
            return new ReflectionMethod($cb, '__invoke');
        }

        return new ReflectionMethod($cb);
    }

    /**
     * Whether a route matches both the requested URL and method.
     */
    private function hasMatch(string $method, string $path, string $base)
    {
        foreach ($this->routes as $route) {
            if (!$route['special'] && false !== $this->match($route, $method, $path, $base)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Methods allowed for the requested URL.
     *
     * @return string[]
     */
    private function allowedMethods(string $path, string $base)
    {
        $allowed = [];

        foreach ($this->routes as $route) {
            if (!$route['special'] && preg_match('#^'.$base.$route['regex'].'$#', $path)) {
                $allowed = array_merge($allowed, $this->routeMethods($route));
            }
        }

        return array_values(array_intersect(static::METHODS, $allowed));
    }

    /**
     * Methods matched by a route.
     *
     * @return string[]
     */
    private function routeMethods(array $route)
    {
        if (!is_null($route['list'])) {
            return $route['list'];
        }

        return array_values(array_filter(static::METHODS, function ($method) use ($route) {
            return preg_match('#'.$route['methods'].'#', $method);
        }));
    }

    /**
     * Response used when no routes match: 404, 405 or the automatic OPTIONS one.
     */
    private function fallback($request, string $method, string $path, string $base)
    {
        $allowed = $this->allowedMethods($path, $base);

        if (empty($allowed)) {
            return $this->service('errors')->response(404);
        }

        if (!in_array('OPTIONS', $allowed)) {
            $allowed[] = 'OPTIONS';
        }

        if ('OPTIONS' === $method) {
            return $this->service('response')->noContent()->withHeader('Allow', implode(', ', $allowed));
        }

        return $this->service('errors')->response(405, null, [], ['Allow' => implode(', ', $allowed)]);
    }

    /**
     * Invoke a route callback through its middlewares.
     */
    private function invoke(array $route, array $args, $request)
    {
        $request->setParams($args['named'] ?? []);

        $handler = function () use ($route, $args) {
            $level = ob_get_level();
            $code = http_response_code();

            ob_start();

            try {
                $result = call_user_func_array($route['callback'], $args['arguments'] ?? []);
            } finally {
                $output = '';

                // buffers left open by the callback are collected too
                while (ob_get_level() > $level) {
                    $output = ob_get_clean().$output;
                }
            }

            // status code set using http_response_code() is preserved
            $status = http_response_code();
            $status = is_int($status) && (!$this->testing || $status !== $code) ? $status : 200;

            return $this->toResponse($result, $output, $status);
        };

        try {
            return $this->pipeline($route['middleware'], $handler, $request);
        } catch (Throwable $exception) {
            return $this->service('errors')->render($exception, $request);
        }
    }

    /**
     * Wrap a callable with middlewares, the first one is the outer one.
     */
    private function pipeline(array $middlewares, callable $core, $request)
    {
        $next = function ($request) use ($core) {
            return $this->toResponse(call_user_func($core, $request));
        };

        foreach (array_reverse($middlewares) as $middleware) {
            $next = function ($request) use ($middleware, $next) {
                return $this->toResponse(call_user_func($middleware, $request, $next));
            };
        }

        return $next($request);
    }

    /**
     * Convert a callback result to a response.
     *
     * Responses are kept, arrays and JsonSerializable objects are encoded as JSON,
     * strings are appended to the output.
     */
    private function toResponse($result, string $output = '', int $status = 200)
    {
        $factory = $this->service('response');

        if ($factory->isResponse($result)) {
            return '' === $output ? $result : $result->withBody($output.$result->getBody());
        }

        if (is_array($result) || $result instanceof JsonSerializable || $result instanceof stdClass) {
            return '' === $output ? $factory->json($result, $status) : $factory->create($output.$factory->json($result)->getBody(), $status);
        }

        if (is_string($result)) {
            $output .= $result;
        }

        return $factory->create($output, $status);
    }

    /**
     * Merge the responses of multiple matched routes.
     */
    private function merge(array $responses)
    {
        $merged = array_shift($responses);

        foreach ($responses as $response) {
            $merged = $merged->withBody($merged->getBody().$response->getBody());

            if (200 !== $response->getStatusCode()) {
                $merged = $merged->withStatus($response->getStatusCode());
            }

            foreach ($response->getHeaders() as $name => $values) {
                $merged = $merged->withHeader($name, $values);
            }
        }

        return $merged;
    }

    /**
     * Convert a route path to an OpenAPI path, false if it isn't possible.
     *
     * @return string|false
     */
    private function openapiPath(string $path, &$parameters)
    {
        $parameters = [];

        $path = preg_replace_callback(
            '#\(\?P?<([a-zA-Z_][a-zA-Z0-9_]*)>((?:[^()]++|\((?2)\))*)\)|\{([a-zA-Z_][a-zA-Z0-9_]*)(?::((?:[^{}]++|\{[^{}]*\})+))?\}#',
            function ($match) use (&$parameters) {
                $name = empty($match[1]) ? $match[3] : $match[1];
                $regex = empty($match[1]) ? ($match[4] ?? '') : $match[2];

                $parameters[] = [
                    'name' => $name,
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => preg_match('#^(\\\\d|\[0-9\])[+*]$#', $regex) ? 'integer' : 'string'],
                ];

                return '{'.$name.'}';
            },
            $path
        );

        // escaped characters are literals
        $path = preg_replace('#\\\\([/.\-])#', '$1', $path);

        if (preg_match('#[()\[\]*+?|^$\\\\]#', $path)) {
            return false;
        }

        return '' === $path ? '/' : $path;
    }
};
