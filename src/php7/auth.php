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
     */
    public function useDatabase(string $table = 'users', string $identifier = 'email')
    {
        $this->setProvider(function ($id) use ($table) {
            return $this->database()->read($table, $id, $this->fields['id']);
        }, function ($value) use ($table, $identifier) {
            return $this->database()->read($table, $value, $identifier);
        });

        $this->onRehash(function ($user, $hash) use ($table) {
            $this->database()->update(
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

    // === AUTHENTICATION ===
    /**
     * Verify the credentials and log in the user.
     */
    public function attempt(string $identifier, string $password): bool
    {
        if (is_null($this->findByIdentifier)) {
            throw new Exception('No provider defined to find users by identifier.');
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
            $this->dispatch('auth:failed', null, $identifier);

            return false;
        }

        if (!is_null($this->rehash) && $this->needsRehash($hash)) {
            call_user_func($this->rehash, $user, $this->hash($password));
        }

        $this->login($user);

        return true;
    }

    /**
     * Log in a user without checking the credentials.
     */
    public function login($user)
    {
        $id = $this->field($user, 'id');

        if (is_null($id)) {
            throw new Exception(sprintf('The user has no "%s" field.', $this->fields['id']));
        }

        if ($this->startSession()) {
            // a new session identifier prevents session fixation
            session_regenerate_id(true);
            $_SESSION[$this->sessionKey] = $id;
        }

        $this->user = $user;
        $this->dispatch('auth:login', $user);
    }

    public function logout()
    {
        $user = $this->user();

        if ($this->startSession()) {
            unset($_SESSION[$this->sessionKey]);
            session_regenerate_id(true);
        }

        $this->user = null;

        if (!is_null($user)) {
            $this->dispatch('auth:logout', $user);
        }
    }

    /**
     * Current user, null when not authenticated.
     */
    public function user()
    {
        if (false !== $this->user) {
            return $this->user;
        }

        $this->user = null;
        $id = $this->startSession() && isset($_SESSION[$this->sessionKey])
            ? $_SESSION[$this->sessionKey]
            : null;

        if (!is_null($id) && !is_null($this->findById)) {
            $this->user = call_user_func($this->findById, $id);

            if (is_null($this->user)) {
                // the user doesn't exist anymore
                unset($_SESSION[$this->sessionKey]);
            }
        }

        return $this->user;
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
     * Start the session if needed, sessions aren't used from the command line.
     */
    private function startSession(): bool
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

        $listener = is_callable($this->deps['listener'])
            ? call_user_func($this->deps['listener'])
            : $this->deps['listener'];

        $listener->dispatch(new class ($name, $user, $identifier) {
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
