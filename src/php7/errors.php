<?php

/**
 * Error handling, enabled by `enable()`.
 *
 * When enabled:
 * - exceptions are reported (5xx only) and rendered as error responses,
 *   with details in debug mode
 * - PHP warnings and notices are converted to ErrorException
 * - requests not matching any route receive a 404 (or 405) response
 *
 * Exceptions with a `getStatusCode()` method (like the ones thrown by
 * `abort()`) set the status code, `getHeaders()` the headers.
 */
$deps['errors'] = new class ($deps) {
    /**
     * Dynamic set of dependencies, used to retrieve the request, response and logger services.
     *
     * @var array
     */
    private $deps;

    /**
     * @var bool
     */
    private $enabled = false;

    /**
     * @var bool
     */
    private $debug = false;

    /**
     * Whether an error response has been sent.
     *
     * @var bool
     */
    private $sent = false;

    /**
     * Custom renderer, receives status, exception (or null) and request, returns a response.
     *
     * @var callable|null
     */
    private $renderer;

    /**
     * Custom reporter, receives the exception.
     *
     * @var callable|null
     */
    private $reporter;

    /**
     * Reason phrases of the most common status codes.
     *
     * @var string[]
     */
    private $phrases = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        409 => 'Conflict',
        410 => 'Gone',
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
     * @param array $deps Dynamic set of dependencies.
     */
    public function __construct(array &$deps = [])
    {
        $this->deps = &$deps;
    }

    /**
     * Enable the error handling.
     *
     * @param bool $debug Show the exceptions details, never enable it in production
     */
    public function enable(bool $debug = false)
    {
        $this->debug = $debug;

        if ($this->enabled) {
            return;
        }

        $this->enabled = true;

        set_error_handler(function ($severity, $message, $file, $line) {
            // errors silenced with @ or excluded by error_reporting, and deprecations
            if (!(error_reporting() & $severity) || in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
                return false;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        // exceptions thrown outside the route callbacks
        if (!empty($_SERVER['REQUEST_URI'])) {
            set_exception_handler(function ($exception) {
                $this->send($this->handle($exception));
            });
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Whether an error response has been sent, the route callbacks aren't invoked after it.
     */
    public function hasSent(): bool
    {
        return $this->sent;
    }

    /**
     * Set a custom renderer, it receives status, exception (or null) and request,
     * it returns a response or null to use the default one.
     */
    public function setRenderer(callable $renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * Set a custom reporter, it receives the exceptions with a 5xx status code.
     * By default they're logged with the logger service.
     */
    public function setReporter(callable $reporter)
    {
        $this->reporter = $reporter;
    }

    /**
     * Stop the request with an error response.
     *
     * @throws RuntimeException Always, with the `getStatusCode` and `getHeaders` methods
     */
    public function abort(int $status, string $message = '', array $headers = [])
    {
        throw new class ($status, $message, $headers) extends RuntimeException {
            private $status;
            private $headers;

            public function __construct(int $status, string $message, array $headers)
            {
                parent::__construct($message);
                $this->status = $status;
                $this->headers = $headers;
            }

            public function getStatusCode(): int
            {
                return $this->status;
            }

            public function getHeaders(): array
            {
                return $this->headers;
            }
        };
    }

    /**
     * Report an exception and render its response.
     *
     * @param Throwable $exception
     *
     * @return object
     */
    public function handle($exception)
    {
        $status = method_exists($exception, 'getStatusCode') ? (int) $exception->getStatusCode() : 500;
        $headers = method_exists($exception, 'getHeaders') ? (array) $exception->getHeaders() : [];

        if ($status >= 500) {
            $this->report($exception);
        }

        $response = $this->response($status, $exception);

        foreach ($headers as $name => $value) {
            $response->setHeader($name, (string) $value);
        }

        return $response;
    }

    /**
     * Response of a request not matching any route.
     *
     * @param string[] $methods Methods (regular expressions) of the routes matching the path
     *
     * @return object
     */
    public function notFound(array $methods = [])
    {
        if (empty($methods)) {
            return $this->response(404);
        }

        $allowed = [];

        foreach ($methods as $method) {
            // only simple lists like GET|POST can be listed
            if (preg_match('#^[A-Za-z]+(\|[A-Za-z]+)*$#', $method)) {
                $allowed = array_merge($allowed, explode('|', strtoupper($method)));
            }
        }

        $response = $this->response(405);

        if (!empty($allowed)) {
            $response->setHeader('Allow', implode(', ', array_unique($allowed)));
        }

        return $response;
    }

    /**
     * Render an error response, JSON when requested by the client, HTML otherwise.
     *
     * @param int            $status
     * @param Throwable|null $exception
     *
     * @return object
     */
    public function response(int $status, $exception = null)
    {
        $request = $this->service('request');

        if (!is_null($this->renderer)) {
            try {
                $response = call_user_func($this->renderer, $status, $exception, $request);

                if (is_object($response)) {
                    return $response;
                }
            } catch (Throwable $error) {
                // the default renderer is used when the custom one fails
                $this->report($error);
            }
        }

        $title = $this->phrases[$status] ?? ($status >= 500 ? 'Server Error' : 'Error');
        // messages of exceptions with a status code below 500 are meant for the client
        $message = !is_null($exception) && ($this->debug || ($status < 500 && method_exists($exception, 'getStatusCode')))
            ? $exception->getMessage()
            : '';

        $response = $this->service('response')->setStatus($status);
        $accept = (string) $request->header('Accept', '');

        if (false !== stripos($accept, 'json') || $request->isJson()) {
            $error = ['status' => $status, 'title' => $title];

            if ('' !== $message) {
                $error['message'] = $message;
            }

            if ($this->debug && !is_null($exception)) {
                $error['exception'] = get_class($exception);
                $error['file'] = $exception->getFile().':'.$exception->getLine();
                $error['trace'] = explode("\n", $exception->getTraceAsString());
            }

            return $response->json(['error' => $error]);
        }

        $e = function ($value) {
            return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        };

        $details = '' === $message ? '' : '<p>'.$e($message).'</p>';

        if ($this->debug && !is_null($exception)) {
            $details .= sprintf(
                '<p><code>%s</code> in <code>%s:%d</code></p><pre>%s</pre>',
                $e(get_class($exception)),
                $e($exception->getFile()),
                $exception->getLine(),
                $e($exception->getTraceAsString())
            );
        }

        return $response
            ->setHeader('Content-Type', 'text/html; charset=utf-8')
            ->setBody(sprintf(
                '<!doctype html><html><head><meta charset="utf-8"><title>%1$d %2$s</title>'
                .'<style>body{font-family:sans-serif;margin:2em;color:#222}pre{overflow:auto;background:#f4f4f4;padding:1em}</style>'
                .'</head><body><h1>%1$d %2$s</h1>%3$s</body></html>',
                $status,
                $e($title),
                $details
            ));
    }

    /**
     * Send an error response, when the headers have already been sent only its body is sent.
     *
     * @param object $response
     */
    public function send($response)
    {
        $this->sent = true;

        if (!headers_sent()) {
            $response->send();

            return;
        }

        echo method_exists($response, 'body') ? $response->body() : '';
    }

    /**
     * Report an exception, using the logger service by default.
     *
     * @param Throwable $exception
     */
    public function report($exception)
    {
        if (!is_null($this->reporter)) {
            call_user_func($this->reporter, $exception);

            return;
        }

        if (!empty($this->deps['logger'])) {
            $this->service('logger')->error('{class}: {text} in {file}:{line}', [
                'class' => get_class($exception),
                'text' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'exception' => $exception,
            ]);
        }
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
