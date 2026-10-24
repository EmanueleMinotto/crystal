<?php

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
