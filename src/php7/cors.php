<?php

/**
 * Cross-Origin Resource Sharing, enabled by `enable()`.
 *
 * When enabled, the CORS headers are sent with every response of the
 * requests coming from an allowed origin, error responses included.
 * OPTIONS requests matching only the path of routes, like the preflight
 * requests, are answered by the router (see `router->options()`).
 *
 * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
 */
$deps['cors'] = new class ($deps) {
    /**
     * Dynamic set of dependencies, used to retrieve the request service.
     *
     * @var array
     */
    private $deps;

    /**
     * @var bool
     */
    private $enabled = false;

    /**
     * Options, see `enable()`.
     *
     * @var array
     */
    private $options = [
        'origins' => [],
        'methods' => [],
        'headers' => [],
        'expose' => [],
        'credentials' => false,
        'max_age' => 0,
        'paths' => '.*',
    ];

    /**
     * @param array $deps Dynamic set of dependencies.
     */
    public function __construct(array &$deps = [])
    {
        $this->deps = &$deps;
    }

    /**
     * Enable the CORS headers.
     *
     * Options:
     * - origins: allowed origins, like `https://example.com`, `https://*.example.com` or `*`
     * - methods: allowed methods, the requested one when empty
     * - headers: allowed request headers, the requested ones when empty
     * - expose: response headers readable by the client
     * - credentials: allow cookies and the `Authorization` header
     * - max_age: seconds the preflight response can be cached, 0 to not send it
     * - paths: regular expression of the paths, relative to the base path like the routes
     *
     * @throws InvalidArgumentException For unknown options
     */
    public function enable(array $options = [])
    {
        $unknown = array_diff_key($options, $this->options);

        if (!empty($unknown)) {
            throw new InvalidArgumentException(sprintf('Unknown CORS options: %s.', implode(', ', array_keys($unknown))));
        }

        $this->options = $options + $this->options;
        $this->enabled = true;

        // headers sent with any response, including the ones of the errors service
        if (!empty($_SERVER['REQUEST_URI']) && !headers_sent()) {
            foreach ($this->headers() as $name => $value) {
                header($name.': '.$value);
            }
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Whether the request is a preflight request.
     */
    public function isPreflight(): bool
    {
        $request = $this->service('request');

        return 'OPTIONS' === $request->method()
            && !is_null($request->header('Origin'))
            && !is_null($request->header('Access-Control-Request-Method'));
    }

    /**
     * CORS headers of the request, empty when CORS isn't enabled or the origin isn't allowed.
     *
     * @return string[] Name => value
     */
    public function headers(): array
    {
        $request = $this->service('request');
        $origin = $request->header('Origin');

        if (!$this->enabled || is_null($origin) || !$this->matchesPath($request->path())) {
            return [];
        }

        $any = in_array('*', $this->options['origins'], true);

        if (!$any && !$this->allows($origin)) {
            return [];
        }

        $vary = [];

        // the wildcard can't be used with credentials
        if ($any && !$this->options['credentials']) {
            $headers = ['Access-Control-Allow-Origin' => '*'];
        } else {
            $headers = ['Access-Control-Allow-Origin' => $origin];
            $vary[] = 'Origin';
        }

        if ($this->options['credentials']) {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        if (!$this->isPreflight()) {
            if (!empty($this->options['expose'])) {
                $headers['Access-Control-Expose-Headers'] = implode(', ', $this->options['expose']);
            }
        } else {
            if (!empty($this->options['methods'])) {
                $headers['Access-Control-Allow-Methods'] = implode(', ', array_map('strtoupper', $this->options['methods']));
            } else {
                $headers['Access-Control-Allow-Methods'] = strtoupper($request->header('Access-Control-Request-Method'));
                $vary[] = 'Access-Control-Request-Method';
            }

            if (!empty($this->options['headers'])) {
                $headers['Access-Control-Allow-Headers'] = implode(', ', $this->options['headers']);
            } elseif ('' !== (string) $request->header('Access-Control-Request-Headers', '')) {
                $headers['Access-Control-Allow-Headers'] = $request->header('Access-Control-Request-Headers');
                $vary[] = 'Access-Control-Request-Headers';
            }

            if ($this->options['max_age'] > 0) {
                $headers['Access-Control-Max-Age'] = (string) $this->options['max_age'];
            }
        }

        if (!empty($vary)) {
            $headers['Vary'] = implode(', ', $vary);
        }

        return $headers;
    }

    /**
     * Whether the origin is allowed, `*` matches one or more labels of the host.
     */
    private function allows(string $origin): bool
    {
        foreach ($this->options['origins'] as $allowed) {
            $pattern = str_replace('\*', '[a-z0-9-]+(?:\.[a-z0-9-]+)*', preg_quote(rtrim($allowed, '/'), '#'));

            if (preg_match('#^'.$pattern.'$#i', $origin)) {
                return true;
            }
        }

        return false;
    }

    private function matchesPath(string $path): bool
    {
        $base = empty($_SERVER['REQUEST_URI']) ? '' : quotemeta(rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\'));

        return (bool) preg_match('#^'.$base.$this->options['paths'].'$#', $path);
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
