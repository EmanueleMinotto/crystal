<?php

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
     * Whether the flash values of the previous request have been aged.
     *
     * @var bool
     */
    private $aged = false;

    /**
     * Start the session if needed.
     *
     * Cookies are `httponly`, `samesite=Lax` (unless configured differently)
     * and `secure` on HTTPS.
     */
    public function start(): bool
    {
        if (!$this->open()) {
            return false;
        }

        // flash values of the previous request are kept for this one only,
        // sessions without flash values aren't changed
        if (!$this->aged) {
            $this->aged = true;

            if (isset($_SESSION['crystal_flash'])) {
                $flash = ['old' => $_SESSION['crystal_flash']['new'] ?? [], 'new' => []];

                if (empty($flash['old'])) {
                    unset($_SESSION['crystal_flash']);
                } else {
                    $_SESSION['crystal_flash'] = $flash;
                }
            }
        }

        return true;
    }

    /**
     * Start the native session with hardened cookies.
     */
    private function open(): bool
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
     * Whether the session would be new and empty: it's not active and the
     * request has no session cookie, so reading it doesn't need to start it.
     */
    private function isNew(): bool
    {
        return PHP_SESSION_ACTIVE !== session_status()
            && empty($this->memory)
            && !empty($_SERVER['REQUEST_URI'])
            && ini_get('session.use_cookies')
            && ini_get('session.use_only_cookies')
            && !isset($_COOKIE[session_name()]);
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
        if ($this->isNew()) {
            return $default;
        }

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
        if ($this->isNew()) {
            return;
        }

        if (!$this->start()) {
            unset($this->memory[$key]);

            return;
        }

        unset($_SESSION[$key]);
    }

    /**
     * Read a value and remove it.
     */
    public function pull(string $key, $default = null)
    {
        $value = $this->get($key, $default);
        $this->remove($key);

        return $value;
    }

    /**
     * Store a value for the current and the next request, like a message
     * shown after a redirect.
     */
    public function flash(string $key, $value)
    {
        $flash = $this->get('crystal_flash', []);
        $flash['new'][$key] = $value;

        $this->set('crystal_flash', $flash);
    }

    /**
     * Read a value stored with `flash()` by this request or by the previous one.
     */
    public function getFlash(string $key, $default = null)
    {
        $flash = $this->get('crystal_flash', []);

        return $flash['new'][$key] ?? $flash['old'][$key] ?? $default;
    }

    /**
     * Store the values of a form for the next request, so the form can be
     * shown again with them. Fields containing "password" aren't stored.
     */
    public function flashInput(array $input)
    {
        $this->flash('crystal:input', array_filter($input, function ($field) {
            return false === stripos((string) $field, 'password');
        }, ARRAY_FILTER_USE_KEY));
    }

    /**
     * Value of a form field stored with `flashInput()`.
     */
    public function old(string $field, $default = null)
    {
        return $this->getFlash('crystal:input', [])[$field] ?? $default;
    }
};
