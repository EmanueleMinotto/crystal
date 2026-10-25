<?php

/**
 * The current HTTP request.
 *
 * Values are read from the superglobals when requested, so changes made
 * to them (by the application or by other services) are always visible.
 */
$deps['request'] = new class ($deps) {
    /**
     * Dynamic set of dependencies, used to retrieve the validator service.
     *
     * @var array
     */
    private $deps;

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
     * @param array $deps Dynamic set of dependencies.
     */
    public function __construct(array &$deps = [])
    {
        $this->deps = &$deps;
    }

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
     *
     * @throws RuntimeException With status code 400, when the JSON body is malformed and the errors handling is enabled
     */
    public function input($key = null, $default = null)
    {
        $input = $_POST;

        if ($this->isJson()) {
            $input = $this->json();

            if (!is_array($input)) {
                $errors = $this->deps['errors'] ?? null;

                // without the errors handling malformed bodies are empty, like before it
                if (is_object($errors) && method_exists($errors, 'isEnabled') && $errors->isEnabled()) {
                    $this->checkJson();
                }

                $input = [];
            }
        }

        return is_null($key) ? $input : ($input[$key] ?? $default);
    }

    /**
     * Validate the query string and body values (they take precedence), returning the validated fields.
     *
     * @param array $rules    Rules of each field, see the validator service
     * @param array $messages Custom messages, keys are "field.rule" or "rule"
     *
     * @throws RuntimeException With status code 422 and the `getErrors` method, when the data isn't valid,
     *                          with status code 400 when the JSON body is malformed
     */
    public function validate(array $rules, array $messages = []): array
    {
        $this->checkJson();

        $validator = $this->deps['validator'];
        $validator = is_callable($validator) ? call_user_func($validator) : $validator;

        return $validator->validate(array_replace($this->query(), $this->input()), $rules, $messages);
    }

    /**
     * Whether the body is JSON, according to the Content-Type header.
     */
    public function isJson(): bool
    {
        return false !== stripos((string) $this->header('Content-Type', ''), 'json');
    }

    /**
     * Whether the body is JSON according to the Content-Type header, but it can't be decoded.
     * Empty bodies aren't malformed.
     */
    public function isMalformedJson(): bool
    {
        if (!$this->isJson() || '' === trim($this->body())) {
            return false;
        }

        json_decode($this->body());

        return JSON_ERROR_NONE !== json_last_error();
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

    /**
     * Stop the request with a 400 response when the JSON body is malformed.
     *
     * @throws RuntimeException With status code 400
     */
    private function checkJson()
    {
        if (!$this->isMalformedJson()) {
            return;
        }

        $message = 'Malformed JSON body: '.json_last_error_msg().'.';
        $errors = $this->deps['errors'] ?? null;
        $errors = is_callable($errors) ? call_user_func($errors) : $errors;

        if (is_object($errors) && method_exists($errors, 'abort')) {
            $errors->abort(400, $message);
        }

        throw new UnexpectedValueException($message);
    }
};
