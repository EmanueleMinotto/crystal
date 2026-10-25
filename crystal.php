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
     * Used to store functions and allow recursive callbacks.
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
     * This variable is a constant during an instance.
     *
     * @var null|string
     */
    private $base;

    /**
     * Whether a route callback has returned a response.
     *
     * @var bool
     */
    private $responded = false;

    public function __construct()
    {
        // services write on $deps, so it has to be bound to the property
        $deps = &$this->deps;

        /**
         * Authentication and authorization built on native sessions and password hashing.
         *
         * Users are loaded through callbacks, so any representation (array, object)
         * and any storage can be used. Only the user identifier is stored in the session.
         * Gates are named callbacks used to authorize actions of the current user.
         *
         * @link https://www.php.net/manual/en/book.session.php
         * @link https://www.php.net/manual/en/book.password.php
         */
        $deps['auth'] = new class ($deps) {
            /**
             * Dynamic set of dependencies, used to retrieve the listener and database services.
             *
             * @var array
             */
            private $deps;

            /**
             * Callback loading a user by its identifier.
             *
             * @var callable|null
             */
            private $findById;

            /**
             * Callback loading a user by its login identifier (email, username, ...).
             *
             * @var callable|null
             */
            private $findByIdentifier;

            /**
             * Callback storing a new password hash for a user.
             *
             * @var callable|null
             */
            private $rehash;

            /**
             * User fields containing the identifier and the password hash.
             *
             * @var string[]
             */
            private $fields = [
                'id' => 'id',
                'password' => 'password',
            ];

            /**
             * Key used to store the user identifier in the session.
             *
             * @var string
             */
            private $sessionKey = 'crystal_auth';

            /**
             * Current user, false when not loaded yet.
             *
             * @var mixed
             */
            private $user = false;

            /**
             * Defined gates, ability => callable.
             *
             * @var callable[]
             */
            private $gates = [];

            /**
             * Hash verified when the user doesn't exist, to keep the same response time.
             *
             * @var string|null
             */
            private $dummyHash;

            /**
             * Callback loading a user by its bearer token.
             *
             * @var callable|null
             */
            private $findByToken;

            /**
             * Whether the current user has been authenticated by a bearer token.
             *
             * @var bool
             */
            private $viaToken = false;

            /**
             * Callbacks storing, finding and removing the remember-me tokens, and their lifetime.
             *
             * @var array|null
             */
            private $remember;

            /**
             * Name of the remember-me cookie.
             *
             * @var string
             */
            private $rememberCookie = 'crystal_remember';

            /**
             * Maximum login attempts and the seconds before they're reset.
             *
             * @var array|null
             */
            private $throttle;

            /**
             * @param array $deps Dynamic set of dependencies.
             */
            public function __construct(array &$deps = [])
            {
                $this->deps = &$deps;
            }

            // === CONFIGURATION ===
            /**
             * Set the callbacks used to load users.
             *
             * @param callable      $findById         Receives the user identifier, returns the user or null
             * @param callable|null $findByIdentifier Receives the login identifier, returns the user or null
             */
            public function setProvider(callable $findById, $findByIdentifier = null)
            {
                assert(is_null($findByIdentifier) || is_callable($findByIdentifier));

                $this->findById = $findById;
                $this->findByIdentifier = $findByIdentifier;
                $this->user = false;
            }

            /**
             * Load users from a table using the database service.
             *
             * Outdated password hashes are updated automatically.
             *
             * @param string      $table      Users table
             * @param string      $identifier Column used to log in
             * @param string|null $token      Column containing the hashed bearer tokens
             */
            public function useDatabase(string $table = 'users', string $identifier = 'email', $token = null)
            {
                assert(is_null($token) || is_string($token));

                $this->setProvider(function ($id) use ($table) {
                    return $this->service('database')->read($table, $id, $this->fields['id']);
                }, function ($value) use ($table, $identifier) {
                    return $this->service('database')->read($table, $value, $identifier);
                });

                if (!is_null($token)) {
                    $this->setTokenProvider(function ($value) use ($table, $token) {
                        return $this->service('database')->read($table, $this->hashToken($value), $token);
                    });
                }

                $this->onRehash(function ($user, $hash) use ($table) {
                    $this->service('database')->update(
                        $table,
                        [$this->fields['password'] => $hash],
                        $this->field($user, 'id'),
                        $this->fields['id']
                    );
                });
            }

            /**
             * Change the user fields containing the identifier and the password hash.
             */
            public function setFields(string $id, string $password)
            {
                $this->fields = [
                    'id' => $id,
                    'password' => $password,
                ];
            }

            /**
             * Set the callback storing a new password hash, it receives the user and the hash.
             */
            public function onRehash(callable $callback)
            {
                $this->rehash = $callback;
            }

            /**
             * Change the key used to store the user identifier in the session.
             */
            public function setSessionKey(string $key)
            {
                $this->sessionKey = $key;
            }

            /**
             * Limit the failed login attempts for each identifier and IP address.
             *
             * @param int $maxAttempts  Failed attempts allowed
             * @param int $decaySeconds Seconds before the attempts are reset
             */
            public function throttle(int $maxAttempts = 5, int $decaySeconds = 60)
            {
                $this->throttle = [
                    'max' => $maxAttempts,
                    'decay' => $decaySeconds,
                ];
            }

            /**
             * Set the callback loading a user by its bearer token, it returns the user or null.
             */
            public function setTokenProvider(callable $findByToken)
            {
                $this->findByToken = $findByToken;
                $this->user = false;
            }

            /**
             * Set the callbacks used to remember users after the session ends.
             *
             * @param callable $save     Receives selector, hashed validator, user identifier and expiration timestamp
             * @param callable $find     Receives the selector, returns ['hash' => ..., 'user_id' => ..., 'expires' => ...] or null
             * @param callable $forget   Receives the selector
             * @param int      $lifetime Seconds before the token expires
             */
            public function setRememberProvider(callable $save, callable $find, callable $forget, int $lifetime = 2592000)
            {
                $this->remember = [
                    'save' => $save,
                    'find' => $find,
                    'forget' => $forget,
                    'lifetime' => $lifetime,
                ];
            }

            /**
             * Store the remember-me tokens in a table using the database service.
             *
             * The table is created on first use.
             */
            public function rememberWithDatabase(string $table = 'crystal_remember_tokens', int $lifetime = 2592000)
            {
                $created = false;
                $database = function () use ($table, &$created) {
                    $database = $this->service('database');

                    if (!$created) {
                        $database->query(sprintf(
                            'CREATE TABLE IF NOT EXISTS %s (selector VARCHAR(255) NOT NULL PRIMARY KEY, hash VARCHAR(255) NOT NULL, user_id VARCHAR(255) NOT NULL, expires INT NOT NULL)',
                            $database->quoteIdentifier($table)
                        ));
                        $created = true;
                    }

                    return $database;
                };

                $this->setRememberProvider(function ($selector, $hash, $id, $expires) use ($database, $table) {
                    $database()->create($table, [
                        'selector' => $selector,
                        'hash' => $hash,
                        'user_id' => $id,
                        'expires' => $expires,
                    ]);
                }, function ($selector) use ($database, $table) {
                    return $database()->read($table, $selector, 'selector');
                }, function ($selector) use ($database, $table) {
                    $database()->delete($table, $selector, 'selector');
                }, $lifetime);
            }

            /**
             * Change the name of the remember-me cookie.
             */
            public function setRememberCookie(string $name)
            {
                $this->rememberCookie = $name;
            }

            // === PASSWORDS ===
            /**
             * @link https://www.php.net/manual/en/function.password-hash.php
             */
            public function hash(string $password): string
            {
                return password_hash($password, PASSWORD_DEFAULT);
            }

            /**
             * @link https://www.php.net/manual/en/function.password-verify.php
             */
            public function verify(string $password, string $hash): bool
            {
                return password_verify($password, $hash);
            }

            /**
             * @link https://www.php.net/manual/en/function.password-needs-rehash.php
             */
            public function needsRehash(string $hash): bool
            {
                return password_needs_rehash($hash, PASSWORD_DEFAULT);
            }

            // === TOKENS ===
            /**
             * Random token, to be given to the user once and stored hashed.
             */
            public function generateToken(): string
            {
                return bin2hex(random_bytes(32));
            }

            public function hashToken(string $token): string
            {
                return hash('sha256', $token);
            }

            // === AUTHENTICATION ===
            /**
             * Verify the credentials and log in the user.
             */
            public function attempt(string $identifier, string $password, bool $remember = false): bool
            {
                if (is_null($this->findByIdentifier)) {
                    throw new Exception('No provider defined to find users by identifier.');
                }

                if ($this->lockedFor($identifier) > 0) {
                    $this->dispatch('auth:lockout', null, $identifier);

                    return false;
                }

                $user = call_user_func($this->findByIdentifier, $identifier);
                $hash = is_null($user) ? null : $this->field($user, 'password');

                if (!is_string($hash)) {
                    // the hash is verified anyway, the response time doesn't reveal if the user exists
                    if (is_null($this->dummyHash)) {
                        $this->dummyHash = $this->hash('');
                    }

                    $hash = $this->dummyHash;
                    $user = null;
                }

                if (!$this->verify($password, $hash) || is_null($user)) {
                    $this->hit($identifier);
                    $this->dispatch('auth:failed', null, $identifier);

                    return false;
                }

                if (!is_null($this->throttle)) {
                    $this->service('cache')->delete($this->throttleKey($identifier));
                }

                if (!is_null($this->rehash) && $this->needsRehash($hash)) {
                    call_user_func($this->rehash, $user, $this->hash($password));
                }

                $this->login($user, $remember);

                return true;
            }

            /**
             * Log in a user without checking the credentials.
             *
             * @param mixed $user     User to log in
             * @param bool  $remember Remember the user after the session ends
             */
            public function login($user, bool $remember = false)
            {
                $id = $this->field($user, 'id');

                if (is_null($id)) {
                    throw new Exception(sprintf('The user has no "%s" field.', $this->fields['id']));
                }

                if ($remember && is_null($this->remember)) {
                    throw new Exception('No provider defined to remember users.');
                }

                $this->renew();
                $this->service('session')->set($this->sessionKey, $id);

                if ($remember) {
                    $this->rememberUser($id);
                }

                $this->user = $user;
                $this->viaToken = false;
                $this->dispatch('auth:login', $user);
            }

            public function logout()
            {
                $user = $this->user();

                $this->service('session')->remove($this->sessionKey);
                $this->renew();
                $this->forgetUser();

                $this->user = null;
                $this->viaToken = false;

                if (!is_null($user)) {
                    $this->dispatch('auth:logout', $user);
                }
            }

            /**
             * Current user, null when not authenticated.
             *
             * Users are authenticated by the bearer token (when a token provider is
             * defined and the request has one), by the session or by the remember-me cookie.
             */
            public function user()
            {
                if (false !== $this->user) {
                    return $this->user;
                }

                $this->user = null;
                $this->viaToken = false;
                $token = $this->bearerToken();

                if (!is_null($token) && !is_null($this->findByToken)) {
                    // the session isn't used by requests with a bearer token
                    $this->user = call_user_func($this->findByToken, $token);
                    $this->viaToken = !is_null($this->user);

                    return $this->user;
                }

                $session = $this->service('session');
                $id = $session->get($this->sessionKey);

                if (!is_null($id) && !is_null($this->findById)) {
                    $this->user = call_user_func($this->findById, $id);

                    if (is_null($this->user)) {
                        // the user doesn't exist anymore
                        $session->remove($this->sessionKey);
                    }
                }

                if (is_null($this->user) && !is_null($this->remember) && !is_null($this->findById)) {
                    $this->user = $this->recallUser();
                }

                return $this->user;
            }

            /**
             * Whether the current user has been authenticated by a bearer token.
             */
            public function viaToken(): bool
            {
                $this->user();

                return $this->viaToken;
            }

            /**
             * Seconds before the identifier can be used again to log in, 0 when it isn't locked.
             */
            public function lockedFor(string $identifier): int
            {
                if (is_null($this->throttle)) {
                    return 0;
                }

                $attempts = $this->service('cache')->get($this->throttleKey($identifier));

                if (!is_array($attempts) || $attempts['count'] < $this->throttle['max']) {
                    return 0;
                }

                return max(0, $attempts['reset'] - time());
            }

            public function check(): bool
            {
                return !is_null($this->user());
            }

            public function id()
            {
                $user = $this->user();

                return is_null($user) ? null : $this->field($user, 'id');
            }

            // === AUTHORIZATION ===
            /**
             * Define a gate, the callback receives the current user and the arguments passed to `can`.
             */
            public function define(string $ability, callable $callback)
            {
                $this->gates[$ability] = $callback;
            }

            /**
             * Check if the current user is authorized, guests are never authorized.
             */
            public function can(string $ability, ...$args): bool
            {
                if (!isset($this->gates[$ability])) {
                    throw new Exception(sprintf('Gate "%s" not defined.', $ability));
                }

                $user = $this->user();

                if (is_null($user)) {
                    return false;
                }

                array_unshift($args, $user);

                return (bool) call_user_func_array($this->gates[$ability], $args);
            }

            /**
             * Wrap a route callback, it's invoked only for authenticated (and authorized) users.
             *
             * Without `$onFail` the response status code is set to 401 (guest) or 403 (forbidden).
             * The gate and `$onFail` receive the same arguments of the route callback.
             *
             * @param callable      $callback Route callback
             * @param callable|null $onFail   Callback invoked when the access is denied
             * @param string|null   $ability  Gate checked after the authentication
             *
             * @return callable
             */
            public function protect(callable $callback, $onFail = null, $ability = null)
            {
                assert(is_null($onFail) || is_callable($onFail));
                assert(is_null($ability) || is_string($ability));

                return new class ($this, $callback, $onFail, $ability) {
                    private $auth;
                    private $callback;
                    private $onFail;
                    private $ability;

                    public function __construct($auth, callable $callback, $onFail, $ability)
                    {
                        $this->auth = $auth;
                        $this->callback = $callback;
                        $this->onFail = $onFail;
                        $this->ability = $ability;
                    }

                    public function __invoke(...$args)
                    {
                        $status = !$this->auth->check() ? 401 : null;

                        if (is_null($status) && !is_null($this->ability) && !$this->auth->can($this->ability, ...$args)) {
                            $status = 403;
                        }

                        if (is_null($status)) {
                            return call_user_func_array($this->callback, $args);
                        }

                        if (!is_null($this->onFail)) {
                            return call_user_func_array($this->onFail, $args);
                        }

                        http_response_code($status);
                    }

                    /**
                     * Wrapped callback, used by the router to read the parameters names.
                     */
                    public function getCrystalCallable()
                    {
                        return is_object($this->callback) && method_exists($this->callback, 'getCrystalCallable')
                            ? $this->callback->getCrystalCallable()
                            : $this->callback;
                    }
                };
            }

            /**
             * Middleware allowing only authenticated (and authorized) users.
             *
             * Guests receive a 401 response, unauthorized users a 403 one.
             * The gate receives the current user and the request.
             *
             * @param string|null $ability Gate checked after the authentication
             *
             * @return callable
             */
            public function middleware($ability = null)
            {
                assert(is_null($ability) || is_string($ability));

                return function ($request, callable $next) use ($ability) {
                    if (!$this->check()) {
                        return $this->service('response')->setStatus(401);
                    }

                    if (!is_null($ability) && !$this->can($ability, $request)) {
                        return $this->service('response')->setStatus(403);
                    }

                    return $next();
                };
            }

            // === INTERNALS ===
            /**
             * Regenerate the session identifier (and the CSRF token) to prevent session fixation.
             */
            private function renew()
            {
                $this->service('session')->regenerate();

                if (!empty($this->deps['csrf'])) {
                    $this->service('csrf')->regenerate();
                }
            }

            /**
             * Bearer token of the `Authorization` header.
             *
             * @return string|null
             */
            private function bearerToken()
            {
                $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

                return preg_match('#^Bearer\s+(\S+)$#i', $header, $matches) ? $matches[1] : null;
            }

            /**
             * Count a failed login attempt.
             */
            private function hit(string $identifier)
            {
                if (is_null($this->throttle)) {
                    return;
                }

                $cache = $this->service('cache');
                $key = $this->throttleKey($identifier);
                $attempts = $cache->get($key);

                if (!is_array($attempts) || $attempts['reset'] <= time()) {
                    $attempts = [
                        'count' => 0,
                        'reset' => time() + $this->throttle['decay'],
                    ];
                }

                ++$attempts['count'];
                $cache->set($key, $attempts, max(1, $attempts['reset'] - time()));
            }

            /**
             * Cache key of the failed login attempts, for the identifier and the IP address.
             */
            private function throttleKey(string $identifier): string
            {
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';

                return 'crystal_auth_throttle.'.sha1(strtolower($identifier).'|'.$ip);
            }

            /**
             * Create a remember-me token and send its cookie.
             *
             * The cookie contains a selector, used to find the token, and a validator
             * stored hashed, so a leaked storage can't be used to log in.
             */
            private function rememberUser($id)
            {
                $selector = bin2hex(random_bytes(12));
                $validator = $this->generateToken();
                $expires = time() + $this->remember['lifetime'];

                call_user_func($this->remember['save'], $selector, $this->hashToken($validator), $id, $expires);
                $this->cookie($selector.':'.$validator, $expires);
            }

            /**
             * Remove the remember-me token of the current request and its cookie.
             */
            private function forgetUser()
            {
                if (is_null($this->remember)) {
                    return;
                }

                $selector = $this->rememberSelector();

                if (!is_null($selector)) {
                    call_user_func($this->remember['forget'], $selector[0]);
                    $this->cookie('', time() - 3600);
                }
            }

            /**
             * Log in the user of the remember-me cookie, the token is replaced by a new one.
             */
            private function recallUser()
            {
                $selector = $this->rememberSelector();

                if (is_null($selector)) {
                    return null;
                }

                list($selector, $validator) = $selector;
                $token = call_user_func($this->remember['find'], $selector);

                if (!is_null($token)) {
                    // tokens are used once, invalid validators can be a theft attempt
                    call_user_func($this->remember['forget'], $selector);
                }

                $user = is_array($token) && (int) $token['expires'] >= time() && hash_equals((string) $token['hash'], $this->hashToken($validator))
                    ? call_user_func($this->findById, $token['user_id'])
                    : null;

                if (is_null($user)) {
                    $this->cookie('', time() - 3600);

                    return null;
                }

                $this->renew();
                $this->service('session')->set($this->sessionKey, $token['user_id']);
                $this->rememberUser($token['user_id']);
                $this->dispatch('auth:login', $user);

                return $user;
            }

            /**
             * Selector and validator of the remember-me cookie.
             *
             * @return string[]|null
             */
            private function rememberSelector()
            {
                $cookie = $_COOKIE[$this->rememberCookie] ?? null;

                if (!is_string($cookie) || !preg_match('#^([a-f0-9]+):([a-f0-9]+)$#', $cookie, $matches)) {
                    return null;
                }

                return [$matches[1], $matches[2]];
            }

            /**
             * Send the remember-me cookie, an empty value removes it.
             */
            private function cookie(string $value, int $expires)
            {
                if ('' === $value) {
                    unset($_COOKIE[$this->rememberCookie]);
                } else {
                    $_COOKIE[$this->rememberCookie] = $value;
                }

                if (empty($_SERVER['REQUEST_URI'])) {
                    return;
                }

                $secure = !empty($_SERVER['HTTPS']) && 'off' !== strtolower($_SERVER['HTTPS']);

                if (PHP_VERSION_ID >= 70300) {
                    setcookie($this->rememberCookie, $value, [
                        'expires' => $expires,
                        'path' => '/',
                        'secure' => $secure,
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]);
                } else {
                    setcookie($this->rememberCookie, $value, $expires, '/', '', $secure, true);
                }
            }

            /**
             * Read a field from an array or an object.
             */
            private function field($user, string $name)
            {
                $field = $this->fields[$name];

                if (is_array($user) || $user instanceof ArrayAccess) {
                    return $user[$field] ?? null;
                }

                return is_object($user) && isset($user->{$field}) ? $user->{$field} : null;
            }

            /**
             * Dispatch an event using the listener service, when available.
             */
            private function dispatch(string $name, $user, $identifier = null)
            {
                if (empty($this->deps['listener'])) {
                    return;
                }

                $this->service('listener')->dispatch(new class ($name, $user, $identifier) {
                    public $name;
                    public $user;
                    public $identifier;

                    public function __construct(string $name, $user, $identifier)
                    {
                        $this->name = $name;
                        $this->user = $user;
                        $this->identifier = $identifier;
                    }

                    public function getCrystalEventName()
                    {
                        return $this->name;
                    }
                });
            }

            /**
             * Retrieve a service, it can be replaced at runtime.
             *
             * @return object
             */
            private function service(string $name)
            {
                $service = $this->deps[$name];

                return is_callable($service)
                    ? call_user_func($service)
                    : $service;
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
         * Cross-Site Request Forgery protection, using a random token stored in the session.
         *
         * The token is read from the `_token` field of the request body or
         * from the `X-CSRF-Token` header.
         *
         * @link https://owasp.org/www-community/attacks/csrf
         */
        $deps['csrf'] = new class ($deps) {
            /**
             * Dynamic set of dependencies, used to retrieve the session and auth services.
             *
             * @var array
             */
            private $deps;

            /**
             * Key used to store the token in the session.
             *
             * @var string
             */
            private $sessionKey = 'crystal_csrf';

            /**
             * Name of the form field containing the token.
             *
             * @var string
             */
            private $fieldName = '_token';

            /**
             * @param array $deps Dynamic set of dependencies.
             */
            public function __construct(array &$deps = [])
            {
                $this->deps = &$deps;
            }

            /**
             * Change the name of the form field containing the token.
             */
            public function setFieldName(string $name)
            {
                $this->fieldName = $name;
            }

            /**
             * Current token, generated when missing.
             */
            public function token(): string
            {
                $token = $this->service('session')->get($this->sessionKey);

                return is_string($token) ? $token : $this->regenerate();
            }

            /**
             * Replace the current token, it's done on login and logout too.
             */
            public function regenerate(): string
            {
                $token = bin2hex(random_bytes(32));
                $this->service('session')->set($this->sessionKey, $token);

                return $token;
            }

            /**
             * Hidden form field containing the token.
             */
            public function field(): string
            {
                return sprintf(
                    '<input type="hidden" name="%s" value="%s">',
                    htmlspecialchars($this->fieldName, ENT_QUOTES),
                    $this->token()
                );
            }

            /**
             * Compare the token with the one stored in the session.
             *
             * @param string|null $token By default it's read from the request
             */
            public function check($token = null): bool
            {
                if (is_null($token)) {
                    $token = $_POST[$this->fieldName] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
                }

                $expected = $this->service('session')->get($this->sessionKey);

                return is_string($token) && is_string($expected) && hash_equals($expected, $token);
            }

            /**
             * Wrap a route callback, it's invoked only when the token is valid.
             *
             * Safe methods (GET, HEAD, OPTIONS) and requests authenticated by a
             * bearer token aren't checked.
             * Without `$onFail` the response status code is set to 403.
             *
             * @param callable      $callback Route callback
             * @param callable|null $onFail   Callback invoked when the token isn't valid
             *
             * @return callable
             */
            public function protect(callable $callback, $onFail = null)
            {
                assert(is_null($onFail) || is_callable($onFail));

                return new class ($this, $callback, $onFail) {
                    private $csrf;
                    private $callback;
                    private $onFail;

                    public function __construct($csrf, callable $callback, $onFail)
                    {
                        $this->csrf = $csrf;
                        $this->callback = $callback;
                        $this->onFail = $onFail;
                    }

                    public function __invoke(...$args)
                    {
                        if ($this->csrf->skip() || $this->csrf->check()) {
                            return call_user_func_array($this->callback, $args);
                        }

                        if (!is_null($this->onFail)) {
                            return call_user_func_array($this->onFail, $args);
                        }

                        http_response_code(403);
                    }

                    /**
                     * Wrapped callback, used by the router to read the parameters names.
                     */
                    public function getCrystalCallable()
                    {
                        return is_object($this->callback) && method_exists($this->callback, 'getCrystalCallable')
                            ? $this->callback->getCrystalCallable()
                            : $this->callback;
                    }
                };
            }

            /**
             * Middleware allowing only requests with a valid token, the others receive a 403 response.
             *
             * Safe methods (GET, HEAD, OPTIONS) and requests authenticated by a
             * bearer token aren't checked.
             *
             * @return callable
             */
            public function middleware()
            {
                return function ($request, callable $next) {
                    if ($this->skip() || $this->check()) {
                        return $next();
                    }

                    return $this->service('response')->setStatus(403);
                };
            }

            /**
             * Check if the current request doesn't need a token.
             */
            public function skip(): bool
            {
                $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

                if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
                    return true;
                }

                // bearer tokens aren't sent automatically by browsers
                return !empty($this->deps['auth']) && $this->service('auth')->viaToken();
            }

            /**
             * Retrieve a service, it can be replaced at runtime.
             *
             * @return object
             */
            private function service(string $name)
            {
                $service = $this->deps[$name];

                return is_callable($service)
                    ? call_user_func($service)
                    : $service;
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
             * Quote a table or column name, using the syntax of the database.
             *
             * Names containing a dot (like `schema.table`) are quoted part by part.
             */
            public function quoteIdentifier(string $name): string
            {
                switch (static::$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) {
                    case 'mysql':
                        // SQLite reads unknown double quoted identifiers as strings
                    case 'sqlite':
                        $quotes = ['`', '`'];

                        break;

                    case 'sqlsrv':
                    case 'dblib':
                        $quotes = ['[', ']'];

                        break;

                    default:
                        // SQL standard, used by PostgreSQL, Oracle, ...
                        $quotes = ['"', '"'];
                }

                return implode('.', array_map(function ($part) use ($quotes) {
                    return $quotes[0].str_replace($quotes[1], $quotes[1].$quotes[1], $part).$quotes[1];
                }, explode('.', $name)));
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
                            ? $this->quoteIdentifier($field)
                            : $field;
                    }, $fields));
                }

                $sql = sprintf('SELECT %s FROM %s', $fields, $this->quoteIdentifier($table));

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
                    'INSERT INTO %s (%s) VALUES (:%s)',
                    $this->quoteIdentifier($table),
                    implode(', ', array_map([$this, 'quoteIdentifier'], $keys)),
                    implode(', :', $keys)
                );

                if ('pgsql' === static::$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) {
                    // lastInsertId() fails without a sequence, aborting the current transaction
                    $rows = $this->query($sql.' RETURNING *', $values);
                    $lastInsertId = $rows[0]['id'] ?? false;
                } else {
                    $this->query($sql, $values);
                    $lastInsertId = static::$pdo->lastInsertId();
                }

                // without auto increment (MySQL returns "0") the inserted id is used
                if (empty($lastInsertId) && isset($values['id'])) {
                    $lastInsertId = $values['id'];
                }

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
                $stmt = static::$pdo->prepare(sprintf(
                    'SELECT * FROM %s WHERE %s LIMIT 1',
                    $this->quoteIdentifier($table),
                    $where['sql']
                ));
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
                    return sprintf('%s = :%s', $this->quoteIdentifier($key), $key);
                }, $keys));

                $where = $this->buildWhereCondition($where, $key);
                $stmt = static::$pdo->prepare(sprintf(
                    'UPDATE %s SET %s WHERE %s',
                    $this->quoteIdentifier($table),
                    $set,
                    $where['sql']
                ));

                return $stmt->execute(array_merge($where['params'], $params));
            }

            /**
             * Delete one or more records based on the shared conditions system.
             */
            public function delete(string $table, $where, $key = 'id')
            {
                $where = $this->buildWhereCondition($where, $key);
                $stmt = static::$pdo->prepare(sprintf(
                    'DELETE FROM %s WHERE %s',
                    $this->quoteIdentifier($table),
                    $where['sql']
                ));

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
                    // [1, 'foo'] => `id` IN (1, "foo")
                    if (is_scalar($value) || array_is_list($value)) {
                        return $this->buildWhereCondition($value, 'id');
                    }

                    if (is_array($value)) {
                        // ['field1 >= ? AND field1 <= ?' => [42, 100], 'field2 LIKE ?' => '%test%'] => (field1 >= ? AND field1 <= ?) AND field2 LIKE ?
                        // ['bar' => 'foo', 'lorem' => 'ipsum'] => `bar` = "foo" AND `lorem` = "ipsum"
                        $sql = '';
                        $params = [];

                        foreach ($value as $k => $v) {
                            if (preg_match('/^[a-zA-Z0-9\_]+$/', $k)) {
                                $v = [$k => $v];
                                $k = sprintf('%s = :%s', $this->quoteIdentifier($k), $k);
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
                        // [], 'bar' => no rows, instead of an invalid `bar` IN ()
                        if (empty($value)) {
                            return [
                                'params' => [],
                                'sql' => '1 = 0',
                            ];
                        }

                        // [1, 2], 'bar' => `bar` IN (1, 2), a placeholder for each value
                        $params = [];

                        foreach (array_values($value) as $index => $item) {
                            $params[$prefixed.'_'.$index] = $item;
                        }

                        return [
                            'params' => $params,
                            'sql' => sprintf('%s IN (%s)', $this->quoteIdentifier($key), implode(', ', array_keys($params))),
                        ];
                    }

                    // 'foo', 'bar' => `bar` = "foo"
                    return [
                        'params' => [
                            $prefixed => $value
                        ],
                        'sql' => sprintf('%s = %s', $this->quoteIdentifier($key), $prefixed),
                    ];
                }
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
         * Middlewares wrapping route callbacks.
         *
         * A middleware receives the request and the next handler, it returns a response:
         *
         *     function ($request, callable $next) {
         *         $response = $next();
         *         $response->setHeader('X-Frame-Options', 'DENY');
         *
         *         return $response;
         *     }
         *
         * `$next()` always returns a response: when the callback doesn't return one,
         * its output is used as the body. In that case, unless a middleware returns
         * another response, the next route callbacks are still invoked.
         */
        $deps['middleware'] = new class ($deps) {
            /**
             * Dynamic set of dependencies, used to retrieve the request and response services.
             *
             * @var array
             */
            private $deps;

            /**
             * @param array $deps Dynamic set of dependencies.
             */
            public function __construct(array &$deps = [])
            {
                $this->deps = &$deps;
            }

            /**
             * Wrap a route callback with middlewares, the first one is the outermost.
             *
             * @param callable $callback       Route callback
             * @param callable ...$middlewares Middlewares
             *
             * @return callable
             */
            public function wrap(callable $callback, callable ...$middlewares)
            {
                return new class ($this->deps, $callback, $middlewares) {
                    private $deps;
                    private $callback;
                    private $middlewares;

                    public function __construct(array &$deps, callable $callback, array $middlewares)
                    {
                        $this->deps = &$deps;
                        $this->callback = $callback;
                        $this->middlewares = $middlewares;
                    }

                    public function __invoke(...$args)
                    {
                        $request = $this->service('request');

                        // response created from the output of a callback not returning one
                        $implicit = null;
                        $status = 200;

                        $handler = function () use ($args, &$implicit, &$status) {
                            ob_start();

                            try {
                                $result = call_user_func_array($this->callback, $args);
                            } catch (Throwable $exception) {
                                ob_end_flush();

                                throw $exception;
                            }

                            $output = ob_get_clean();

                            if (is_object($result) && method_exists($result, 'send')) {
                                if ('' !== $output && method_exists($result, 'body') && method_exists($result, 'setBody')) {
                                    $result->setBody($output.$result->body());
                                }

                                return $result;
                            }

                            $status = is_int(http_response_code()) ? http_response_code() : 200;
                            $implicit = $this->service('response')
                                ->setStatus($status)
                                ->setBody($output);

                            return $implicit;
                        };

                        foreach (array_reverse($this->middlewares) as $middleware) {
                            $handler = function () use ($middleware, $request, $handler) {
                                return call_user_func($middleware, $request, $handler);
                            };
                        }

                        $response = $handler();

                        if (!is_null($implicit) && (!is_object($response) || $response === $implicit)) {
                            // the callback didn't return a response: the next callbacks aren't skipped
                            if ($implicit->status() === $status && empty($implicit->headers()) && empty($implicit->cookies())) {
                                // unchanged by the middlewares
                                echo $implicit->body();
                            } else {
                                $implicit->send();
                            }

                            return null;
                        }

                        return $response;
                    }

                    /**
                     * Wrapped callback, used by the router to read the parameters names.
                     */
                    public function getCrystalCallable()
                    {
                        return is_object($this->callback) && method_exists($this->callback, 'getCrystalCallable')
                            ? $this->callback->getCrystalCallable()
                            : $this->callback;
                    }

                    /**
                     * Retrieve a service, it can be replaced at runtime.
                     *
                     * @return object
                     */
                    private function service(string $name)
                    {
                        $service = $this->deps[$name];

                        return is_callable($service)
                            ? call_user_func($service)
                            : $service;
                    }
                };
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
                $database = $this->database();
                $database->query(sprintf(
                    'CREATE TABLE IF NOT EXISTS %s (version VARCHAR(255) NOT NULL PRIMARY KEY, applied_at INT NOT NULL)',
                    $database->quoteIdentifier($this->table)
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
         * The current HTTP request.
         *
         * Values are read from the superglobals when requested, so changes made
         * to them (by the application or by other services) are always visible.
         */
        $deps['request'] = new class () {
            /**
             * Raw body, null when not read yet.
             *
             * @var string|null
             */
            private $body;

            /**
             * Named parameters of the current route.
             *
             * @var array
             */
            private $params = [];

            /**
             * Custom values, i.e. shared by middlewares.
             *
             * @var array
             */
            private $attributes = [];

            /**
             * Request method, uppercase.
             */
            public function method(): string
            {
                return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
            }

            /**
             * Requested path, without the query string.
             */
            public function path(): string
            {
                $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

                return is_string($path) ? $path : '/';
            }

            public function isSecure(): bool
            {
                return !empty($_SERVER['HTTPS']) && 'off' !== strtolower($_SERVER['HTTPS']);
            }

            /**
             * Client IP address, proxies headers aren't trusted.
             *
             * @return string|null
             */
            public function ip()
            {
                return $_SERVER['REMOTE_ADDR'] ?? null;
            }

            /**
             * Query string values, all of them without a key.
             */
            public function query($key = null, $default = null)
            {
                return is_null($key) ? $_GET : ($_GET[$key] ?? $default);
            }

            /**
             * Body values, from a form or from a JSON object, all of them without a key.
             */
            public function input($key = null, $default = null)
            {
                $input = $_POST;

                if ($this->isJson()) {
                    $input = $this->json();
                    $input = is_array($input) ? $input : [];
                }

                return is_null($key) ? $input : ($input[$key] ?? $default);
            }

            /**
             * Whether the body is JSON, according to the Content-Type header.
             */
            public function isJson(): bool
            {
                return false !== stripos((string) $this->header('Content-Type', ''), 'json');
            }

            /**
             * Decoded JSON body, objects are decoded as arrays.
             */
            public function json()
            {
                return json_decode($this->body(), true);
            }

            /**
             * Raw body.
             */
            public function body(): string
            {
                if (is_null($this->body)) {
                    $this->body = (string) file_get_contents('php://input');
                }

                return $this->body;
            }

            /**
             * Replace the raw body, i.e. in tests.
             */
            public function setBody(string $body)
            {
                $this->body = $body;
            }

            /**
             * Request header, names are case insensitive.
             */
            public function header(string $name, $default = null)
            {
                $key = strtoupper(str_replace('-', '_', $name));

                // these headers don't have the HTTP_ prefix
                if (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) && isset($_SERVER[$key])) {
                    return $_SERVER[$key];
                }

                return $_SERVER['HTTP_'.$key] ?? $default;
            }

            public function cookie(string $name, $default = null)
            {
                return $_COOKIE[$name] ?? $default;
            }

            /**
             * Uploaded file, as described in $_FILES.
             *
             * @return array|null
             */
            public function file(string $name)
            {
                return $_FILES[$name] ?? null;
            }

            /**
             * Named parameter of the current route.
             */
            public function param(string $name, $default = null)
            {
                return $this->params[$name] ?? $default;
            }

            /**
             * Named parameters of the current route.
             */
            public function params(): array
            {
                return $this->params;
            }

            /**
             * Set by the router before invoking each route callback.
             */
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
            }
        };

        /**
         * HTTP responses, `$mf('response')` returns a new response each time.
         *
         * Status, headers and cookies are sent only by `send`, so they can be
         * changed at any time. Route callbacks can return a response, it's sent
         * by the router.
         */
        $deps['response'] = function () {
            return new class () {
                /**
                 * @var int
                 */
                private $status = 200;

                /**
                 * Headers, lowercase name => [name, value].
                 *
                 * @var array
                 */
                private $headers = [];

                /**
                 * Cookies, name => [value, options].
                 *
                 * @var array
                 */
                private $cookies = [];

                /**
                 * @var string
                 */
                private $body = '';

                public function status(): int
                {
                    return $this->status;
                }

                public function setStatus(int $status)
                {
                    $this->status = $status;

                    return $this;
                }

                /**
                 * Response header, names are case insensitive.
                 *
                 * @return string|null
                 */
                public function header(string $name)
                {
                    $key = strtolower($name);

                    return isset($this->headers[$key]) ? $this->headers[$key][1] : null;
                }

                /**
                 * All the headers, name => value.
                 */
                public function headers(): array
                {
                    return array_column($this->headers, 1, 0);
                }

                public function setHeader(string $name, string $value)
                {
                    $this->headers[strtolower($name)] = [$name, $value];

                    return $this;
                }

                public function removeHeader(string $name)
                {
                    unset($this->headers[strtolower($name)]);

                    return $this;
                }

                /**
                 * Add a cookie, options are the ones of setcookie().
                 *
                 * By default cookies are `httponly`, `samesite=Lax`, `secure` on HTTPS
                 * and valid for the whole domain.
                 *
                 * @link https://www.php.net/manual/en/function.setcookie.php
                 */
                public function cookie(string $name, string $value, array $options = [])
                {
                    $this->cookies[$name] = [$value, $options + [
                        'expires' => 0,
                        'path' => '/',
                        'domain' => '',
                        'secure' => !empty($_SERVER['HTTPS']) && 'off' !== strtolower($_SERVER['HTTPS']),
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]];

                    return $this;
                }

                /**
                 * Remove a cookie from the client.
                 */
                public function forgetCookie(string $name, array $options = [])
                {
                    return $this->cookie($name, '', ['expires' => time() - 3600] + $options);
                }

                /**
                 * Cookies, name => [value, options].
                 */
                public function cookies(): array
                {
                    return $this->cookies;
                }

                public function body(): string
                {
                    return $this->body;
                }

                public function setBody(string $body)
                {
                    $this->body = $body;

                    return $this;
                }

                /**
                 * Append content to the body.
                 */
                public function write(string $content)
                {
                    $this->body .= $content;

                    return $this;
                }

                /**
                 * JSON body, with the Content-Type header.
                 *
                 * @param mixed    $data   Data to encode
                 * @param int|null $status Status code, unchanged when null
                 */
                public function json($data, $status = null)
                {
                    assert(is_null($status) || is_int($status));

                    $json = json_encode($data);

                    if (false === $json) {
                        throw new InvalidArgumentException('Unable to encode the JSON body: '.json_last_error_msg());
                    }

                    if (!is_null($status)) {
                        $this->status = $status;
                    }

                    $this->setHeader('Content-Type', 'application/json');

                    return $this->setBody($json);
                }

                /**
                 * Redirect to another URL.
                 */
                public function redirect(string $url, int $status = 302)
                {
                    $this->status = $status;

                    return $this->setHeader('Location', $url);
                }

                /**
                 * Send status, headers, cookies and body.
                 */
                public function send()
                {
                    http_response_code($this->status);

                    foreach ($this->headers as $header) {
                        header($header[0].': '.$header[1]);
                    }

                    foreach ($this->cookies as $name => $cookie) {
                        list($value, $options) = $cookie;

                        if (PHP_VERSION_ID >= 70300) {
                            setcookie($name, $value, $options);
                        } else {
                            // samesite isn't supported before PHP 7.3
                            setcookie($name, $value, $options['expires'], $options['path'], $options['domain'], $options['secure'], $options['httponly']);
                        }
                    }

                    echo $this->body;
                }
            };
        };

        /**
         * Named routes, URL generation and groups of routes.
         *
         * URLs can be generated only for routes made of literal characters,
         * escaped characters and named subpatterns, like `/posts/(?P<id>[0-9]+)`.
         */
        $deps['router'] = new class ($deps, $this) {
            /**
             * Dynamic set of dependencies, used to retrieve the middleware service.
             *
             * @var array
             */
            private $deps;

            /**
             * The framework instance, used to define routes.
             *
             * @var callable
             */
            private $mf;

            /**
             * Named routes, name => regular expression.
             *
             * @var string[]
             */
            private $routes = [];

            /**
             * @param array    $deps Dynamic set of dependencies.
             * @param callable $mf   The framework instance.
             */
            public function __construct(array &$deps, callable $mf)
            {
                $this->deps = &$deps;
                $this->mf = $mf;
            }

            /**
             * Define a named route, arguments are the same of the framework instance.
             */
            public function route(string $name, string $regex, callable $callback, string $method = 'GET', $priority = 0)
            {
                $this->name($name, $regex);

                return call_user_func($this->mf, $regex, $callback, $method, $priority);
            }

            /**
             * Give a name to a route regular expression.
             *
             * @throws InvalidArgumentException If the name is already used
             */
            public function name(string $name, string $regex)
            {
                if (isset($this->routes[$name])) {
                    throw new InvalidArgumentException(sprintf('Route "%s" already defined.', $name));
                }

                $this->routes[$name] = $regex;
            }

            public function has(string $name): bool
            {
                return isset($this->routes[$name]);
            }

            /**
             * Generate the URL of a named route.
             *
             * Values are URL encoded and must match their subpattern,
             * parameters not used by the route are added to the query string.
             *
             * @throws InvalidArgumentException If the route isn't defined, a parameter is missing or invalid
             * @throws LogicException           If the route can't be reversed
             */
            public function url(string $name, array $params = []): string
            {
                if (!isset($this->routes[$name])) {
                    throw new InvalidArgumentException(sprintf('Route "%s" not defined.', $name));
                }

                $path = '';

                foreach ($this->parse($name, $this->routes[$name]) as $part) {
                    if (is_string($part)) {
                        $path .= $part;

                        continue;
                    }

                    list($param, $pattern) = $part;

                    if (!array_key_exists($param, $params)) {
                        throw new InvalidArgumentException(sprintf('Missing parameter "%s" for route "%s".', $param, $name));
                    }

                    // the router matches the URL encoded path
                    $value = rawurlencode((string) $params[$param]);
                    unset($params[$param]);

                    if (!preg_match('#^(?:'.$pattern.')$#', $value)) {
                        throw new InvalidArgumentException(sprintf(
                            'Parameter "%s" of route "%s" must match "%s", "%s" given.',
                            $param,
                            $name,
                            $pattern,
                            $value
                        ));
                    }

                    $path .= $value;
                }

                $base = empty($_SERVER['REQUEST_URI']) ? '' : rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
                $query = empty($params) ? '' : '?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);

                return $base.$path.$query;
            }

            /**
             * Define a group of routes, sharing a prefix of the path, a prefix
             * of the names and middlewares (the first one is the outermost).
             *
             * The callback receives an object with the same methods of the framework
             * instance for routes (with the name as fifth argument), `route` and `group`.
             *
             * @param string     $prefix      Prefix of the regular expressions
             * @param callable   $define      Callback defining the routes
             * @param callable[] $middlewares Middlewares of the routes
             * @param string     $name        Prefix of the names
             */
            public function group(string $prefix, callable $define, array $middlewares = [], string $name = '')
            {
                $define(new class ($this, $this->deps, $prefix, $middlewares, $name) {
                    private $router;
                    private $deps;
                    private $prefix;
                    private $middlewares;
                    private $name;

                    public function __construct($router, array &$deps, string $prefix, array $middlewares, string $name)
                    {
                        $this->router = $router;
                        $this->deps = &$deps;
                        $this->prefix = $prefix;
                        $this->middlewares = $middlewares;
                        $this->name = $name;
                    }

                    public function __invoke(string $regex, callable $callback, string $method = 'GET', $priority = 0, $name = null)
                    {
                        assert(is_null($name) || is_string($name));

                        if (!empty($this->middlewares)) {
                            $middleware = $this->deps['middleware'];
                            $middleware = is_callable($middleware) ? call_user_func($middleware) : $middleware;
                            $callback = $middleware->wrap($callback, ...$this->middlewares);
                        }

                        if (is_null($name)) {
                            return call_user_func($this->router->instance(), $this->prefix.$regex, $callback, $method, $priority);
                        }

                        return $this->router->route($this->name.$name, $this->prefix.$regex, $callback, $method, $priority);
                    }

                    public function route(string $name, string $regex, callable $callback, string $method = 'GET', $priority = 0)
                    {
                        return $this($regex, $callback, $method, $priority, $name);
                    }

                    /**
                     * Nested group, prefixes are concatenated and the outer middlewares are the outermost.
                     */
                    public function group(string $prefix, callable $define, array $middlewares = [], string $name = '')
                    {
                        $this->router->group(
                            $this->prefix.$prefix,
                            $define,
                            array_merge($this->middlewares, $middlewares),
                            $this->name.$name
                        );
                    }
                });
            }

            /**
             * The framework instance, used by groups to define routes.
             *
             * @return callable
             */
            public function instance()
            {
                return $this->mf;
            }

            /**
             * Split a route in literal parts and [name, subpattern] parameters.
             *
             * @throws LogicException If the route contains other regular expression constructs
             */
            private function parse(string $name, string $regex): array
            {
                $parts = [];
                $literal = '';
                $length = strlen($regex);
                $fail = function ($reason) use ($name, $regex) {
                    return new LogicException(sprintf('The URL of route "%s" (%s) can\'t be generated: %s.', $name, $regex, $reason));
                };

                for ($i = 0; $i < $length; ++$i) {
                    $char = $regex[$i];

                    if ('\\' === $char) {
                        $next = $regex[$i + 1] ?? '';

                        // \d, \w, \s, ... are character classes
                        if ('' === $next || ctype_alnum($next)) {
                            throw $fail('character classes are allowed only in named subpatterns');
                        }

                        $literal .= $next;
                        ++$i;

                        continue;
                    }

                    if ('(' === $char) {
                        if (!preg_match('#\G\(\?P?(?:<([a-zA-Z_][a-zA-Z0-9_]*)>|\'([a-zA-Z_][a-zA-Z0-9_]*)\')#', $regex, $matches, 0, $i)) {
                            throw $fail('only named subpatterns are allowed');
                        }

                        $end = $this->closingParenthesis($regex, $i);

                        if (is_null($end)) {
                            throw $fail('unbalanced parentheses');
                        }

                        if (isset($regex[$end + 1]) && false !== strpos('?*+{', $regex[$end + 1])) {
                            throw $fail('quantifiers aren\'t allowed after named subpatterns');
                        }

                        if ('' !== $literal) {
                            $parts[] = $literal;
                            $literal = '';
                        }

                        $start = $i + strlen($matches[0]);
                        $parts[] = ['' !== $matches[1] ? $matches[1] : $matches[2], substr($regex, $start, $end - $start)];
                        $i = $end;

                        continue;
                    }

                    if (false !== strpos('.[]()|*+?{}^$', $char)) {
                        throw $fail(sprintf('"%s" isn\'t allowed outside named subpatterns', $char));
                    }

                    $literal .= $char;
                }

                if ('' !== $literal) {
                    $parts[] = $literal;
                }

                return $parts;
            }

            /**
             * Position of the parenthesis closing the one at `$start`,
             * escaped characters and character classes are skipped.
             *
             * @return int|null
             */
            private function closingParenthesis(string $regex, int $start)
            {
                $depth = 0;
                $class = false;
                $length = strlen($regex);

                for ($i = $start; $i < $length; ++$i) {
                    $char = $regex[$i];

                    if ('\\' === $char) {
                        ++$i;
                    } elseif ($class) {
                        $class = ']' !== $char;
                    } elseif ('[' === $char) {
                        $class = true;

                        // a "]" right after "[" or "[^" is a literal character
                        if (']' === ($regex[$i + 1] ?? '')) {
                            ++$i;
                        } elseif ('^' === ($regex[$i + 1] ?? '') && ']' === ($regex[$i + 2] ?? '')) {
                            $i += 2;
                        }
                    } elseif ('(' === $char) {
                        ++$depth;
                    } elseif (')' === $char && 0 === --$depth) {
                        return $i;
                    }
                }

                return null;
            }
        };

        /**
         * Native sessions, started only when needed with hardened cookies.
         *
         * From the command line sessions aren't used: values are kept in memory
         * for the current execution.
         *
         * @link https://www.php.net/manual/en/book.session.php
         */
        $deps['session'] = new class () {
            /**
             * Values used when sessions aren't available.
             *
             * @var array
             */
            private $memory = [];

            /**
             * Start the session if needed.
             *
             * Cookies are `httponly`, `samesite=Lax` (unless configured differently)
             * and `secure` on HTTPS.
             */
            public function start(): bool
            {
                if (PHP_SESSION_ACTIVE === session_status()) {
                    return true;
                }

                if (empty($_SERVER['REQUEST_URI']) || PHP_SESSION_DISABLED === session_status() || headers_sent()) {
                    return false;
                }

                if (!ini_get('session.use_cookies')) {
                    return session_start();
                }

                $params = session_get_cookie_params();
                $secure = $params['secure'] || (!empty($_SERVER['HTTPS']) && 'off' !== strtolower($_SERVER['HTTPS']));

                if (PHP_VERSION_ID >= 70300) {
                    session_set_cookie_params([
                        'lifetime' => $params['lifetime'],
                        'path' => $params['path'],
                        'domain' => $params['domain'],
                        'secure' => $secure,
                        'httponly' => true,
                        'samesite' => empty($params['samesite']) ? 'Lax' : $params['samesite'],
                    ]);
                } else {
                    session_set_cookie_params($params['lifetime'], $params['path'], $params['domain'], $secure, true);
                }

                return session_start();
            }

            /**
             * Change the session identifier keeping its values, it prevents session fixation.
             */
            public function regenerate(): bool
            {
                return $this->start() && session_regenerate_id(true);
            }

            public function get(string $key, $default = null)
            {
                if (!$this->start()) {
                    return $this->memory[$key] ?? $default;
                }

                return $_SESSION[$key] ?? $default;
            }

            public function set(string $key, $value)
            {
                if (!$this->start()) {
                    $this->memory[$key] = $value;

                    return;
                }

                $_SESSION[$key] = $value;
            }

            public function has(string $key): bool
            {
                return !is_null($this->get($key));
            }

            public function remove(string $key)
            {
                if (!$this->start()) {
                    unset($this->memory[$key]);

                    return;
                }

                unset($_SESSION[$key]);
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
    }

    public function __invoke()
    {
        // local references used to keep the function body unchanged
        $deploy = &$this->deploy;
        $matches = &$this->matches;
        $deps = &$this->deps;
        $base = &$this->base;
        $responded = &$this->responded;

        // base path for each route defined once
        if (is_null($base)) {
            $base = quotemeta(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'));
        }

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
                        if (!empty($_SERVER['REQUEST_URI'])) {
                            return '(?!('.implode('|', $matches).')$).*';
                        }

                        break;
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

        // routes can be named using the fifth argument
        if (func_num_args() >= 5 && is_string($args[0]) && !is_null($args[4])) {
            $deps['router']->name($args[4], $args[0]);
        }

        if (!is_null($deploy)) {
            goto invoke_deploy;
        }

        // functions have to be stored only once
        if (empty($_SERVER['REQUEST_URI'])) {
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

            goto invoke_deploy;
        }

        /**
         * Function invoking route callbacks.
         *
         * The route parameters are exposed by the request service, the first
         * response returned by a callback is sent and the next callbacks are skipped.
         *
         * @param callback $cb     Route callback
         * @param array    $args   Arguments of the callback
         * @param array    $params Named route parameters
         *
         * @return void
         */
        $dispatch = function ($cb, array $args, array $params) use (&$deps, &$responded) {
            // the output of all the callbacks is buffered until the end of
            // the script, so a response can set status and headers after it
            static $buffered = false;

            if (!$buffered) {
                $buffered = ob_start();
            }

            if ($responded) {
                return;
            }

            if (!empty($deps['request']) && is_object($deps['request']) && method_exists($deps['request'], 'setParams')) {
                $deps['request']->setParams($params);
            }

            ob_start();

            try {
                $result = call_user_func_array($cb, $args);
            } catch (Throwable $exception) {
                ob_end_flush();

                throw $exception;
            }

            $output = ob_get_clean();

            if (!is_object($result) || !method_exists($result, 'send')) {
                echo $output;

                return;
            }

            $responded = true;

            // the output of the callback is the beginning of the response body
            if ('' !== $output && method_exists($result, 'body') && method_exists($result, 'setBody')) {
                $result->setBody($output.$result->body());
                $output = '';
            }

            $result->send();
            echo $output;
        };

        /**
         * Function used as a router.
         *
         * @param string   $regex    Regular expression used to match requested URL
         * @param callback $cb       Function invoked when there's a match
         * @param string   $method   Request method(s)
         * @param float    $priority Set `$cb` priority from 0 (high) to ~1.8e308 (low)
         *
         * @link http://php.net/manual/en/language.types.float.php
         *
         * @return void
         */
        $deploy = function ($regex, $cb, $method = 'GET', $priority = 0) use (&$deploy, &$matches, $base, $dispatch) {
            // Checking well formed call
            assert(is_string($regex));
            assert(is_callable($cb));
            assert(is_string($method));
            assert(is_numeric($priority));

            // match stored as unique using the Adler-32 algorithm that is faster than md5
            // http://en.wikipedia.org/wiki/Adler-32
            // http://3v4l.org/7MC3j
            $matches[hash('adler32', $regex)] = $regex;

            if ($priority > 0) {
                // Recursion is used to set callback priority
                return register_shutdown_function($deploy, $regex, $cb, $method, $priority - 1);
            }

            if (
                preg_match('#'.$method.'#', $_SERVER['REQUEST_METHOD']) &&
                preg_match('#^'.$base.$regex.'$#', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), $submatches)
            ) {
                // Named subpatterns are allowed
                // http://it2.php.net/manual/en/regexp.reference.subpatterns.php
                $named = [];

                foreach ($submatches as $key => $value) {
                    if (is_string($key)) {
                        $named[$key] = $value;
                    }
                }

                $submatches = array_unique($submatches);
                // If matches is provided, then it is filled with the results of search.
                // $submatches[0] will contain the text that matched the full pattern,
                // $submatches[1] will have the text that matched the first captured parenthesized
                // subpattern, and so on.
                unset($submatches[0]);

                // Wrappers (like protected routes) expose the wrapped callback,
                // so its parameters can be used for named subpatterns.
                $target = is_object($cb) && method_exists($cb, 'getCrystalCallable')
                    ? $cb->getCrystalCallable()
                    : $cb;

                // Snippet used to extract parameter from a callable object.
                if ((is_string($target) && function_exists($target)) || $target instanceof Closure) {
                    $reflector = new ReflectionFunction($target);
                } elseif (is_array($target)) {
                    $reflector = new ReflectionMethod($target[0], $target[1]);
                } elseif (is_object($target)) {
                    $reflector = new ReflectionMethod($target, '__invoke');
                } else {
                    $reflector = new ReflectionMethod($target);
                }
                $params = [];

                foreach ($reflector->getParameters() as $parameter) {
                    // reset to prevent key value
                    $params[$parameter->name] = null;
                }

                // user can use named parameters only if explicitly requested
                if (array_intersect(array_keys($params), array_keys($submatches))) {
                    $submatches = array_merge($params, $submatches);
                }

                // register_shutdown_function is used to call added functions when script ends
                // http://it2.php.net/manual/en/function.register-shutdown-function.php
                return register_shutdown_function($dispatch, $cb, array_values($submatches), $named);
            }
        };

        // invoking deploy
        invoke_deploy:
        return call_user_func_array($deploy, func_get_args());
    }
};
