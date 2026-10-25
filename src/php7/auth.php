<?php

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
