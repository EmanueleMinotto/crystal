<?php

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
