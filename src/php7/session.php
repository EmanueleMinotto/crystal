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
};
