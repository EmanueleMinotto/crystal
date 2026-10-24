<?php

/**
 * Exposes HTTP errors handling via $deps['errors'].
 *
 * Errors are rendered as Problem Details (RFC 9457) JSON documents,
 * or as a minimal HTML page when the client accepts text/html.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9457
 */
$deps['errors'] = new class ($this) {
    /**
     * Framework instance, used to resolve other services.
     *
     * @var callable
     */
    private $mf;

    /**
     * Custom handler `function (Throwable $e, $request)` returning a response.
     *
     * @var callable|null
     */
    private $handler;

    /**
     * Expose exception details in 500 responses.
     *
     * @var bool
     */
    private $debug = false;

    /**
     * One of: auto, json, html.
     *
     * @var string
     */
    private $format = 'auto';

    public function __construct(callable $mf)
    {
        $this->mf = $mf;
    }

    /**
     * Create an exception rendered with the given status code.
     *
     * @param int            $status     HTTP status code
     * @param string         $detail     Explanation of the problem
     * @param array          $extensions Additional Problem Details members
     * @param array          $headers    Additional response headers
     * @param Throwable|null $previous   Previous exception
     *
     * @return RuntimeException
     */
    public function create(int $status, string $detail = '', array $extensions = [], array $headers = [], $previous = null)
    {
        return new class ($status, $detail, $extensions, $headers, $previous) extends RuntimeException {
            private $status;
            private $extensions;
            private $headers;

            public function __construct(int $status, string $detail, array $extensions, array $headers, $previous)
            {
                parent::__construct($detail, $status, $previous);

                $this->status = $status;
                $this->extensions = $extensions;
                $this->headers = $headers;
            }

            public function getStatusCode()
            {
                return $this->status;
            }

            public function getExtensions()
            {
                return $this->extensions;
            }

            public function getHeaders()
            {
                return $this->headers;
            }
        };
    }

    /**
     * Throw an exception rendered with the given status code.
     *
     * @throws RuntimeException
     */
    public function abort(int $status, string $detail = '', array $extensions = [], array $headers = [])
    {
        throw $this->create($status, $detail, $extensions, $headers);
    }

    /**
     * Set a custom handler, returning null falls back to the default rendering.
     */
    public function setHandler($handler)
    {
        if (!is_null($handler) && !is_callable($handler)) {
            throw new InvalidArgumentException('Error handler must be callable or null');
        }

        $this->handler = $handler;
    }

    public function setDebug(bool $debug)
    {
        $this->debug = $debug;
    }

    /**
     * @param string $format One of: auto, json, html
     */
    public function setFormat(string $format)
    {
        if (!in_array($format, ['auto', 'json', 'html'])) {
            throw new InvalidArgumentException(sprintf('Unknown errors format "%s"', $format));
        }

        $this->format = $format;
    }

    /**
     * Create the response for an error status code.
     *
     * @param int         $status     HTTP status code
     * @param string|null $detail     Explanation of the problem
     * @param array       $extensions Additional Problem Details members
     * @param array       $headers    Additional response headers
     *
     * @return object
     */
    public function response(int $status, $detail = null, array $extensions = [], array $headers = [])
    {
        $factory = call_user_func($this->mf, 'response');
        $format = $this->format;

        if ('auto' === $format) {
            $request = call_user_func($this->mf, 'request');
            $format = !$request->wantsJson() && $request->accepts('text/html') ? 'html' : 'json';
        }

        if ('json' === $format) {
            return $factory->problem($status, $detail, $extensions, $headers);
        }

        $title = htmlspecialchars($status.' '.$factory->reasonPhrase($status), ENT_QUOTES, 'UTF-8');
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>'.$title.'</title></head><body><h1>'.$title.'</h1>';

        if (!is_null($detail) && '' !== $detail) {
            $html .= '<p>'.htmlspecialchars($detail, ENT_QUOTES, 'UTF-8').'</p>';
        }

        return $factory->html($html.'</body></html>', $status, $headers);
    }

    /**
     * Convert an exception to a response.
     *
     * Exceptions exposing getStatusCode() are rendered with their status code,
     * other exceptions are logged and rendered as 500 errors.
     *
     * @return object
     */
    public function render(Throwable $exception, $request = null)
    {
        if (!is_null($this->handler)) {
            $response = call_user_func($this->handler, $exception, $request ?: call_user_func($this->mf, 'request'));

            if (!is_null($response)) {
                $factory = call_user_func($this->mf, 'response');

                return $factory->isResponse($response) ? $response : $factory->json($response);
            }
        }

        if (method_exists($exception, 'getStatusCode') && is_int($status = $exception->getStatusCode()) && $status >= 400 && $status < 600) {
            return $this->response(
                $status,
                $exception->getMessage(),
                method_exists($exception, 'getExtensions') ? $exception->getExtensions() : [],
                method_exists($exception, 'getHeaders') ? $exception->getHeaders() : []
            );
        }

        call_user_func($this->mf, 'logger')->error('Uncaught {class}: {message} in {file}:{line}', [
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);

        if (!$this->debug) {
            return $this->response(500);
        }

        return $this->response(500, $exception->getMessage(), [
            'exception' => [
                'class' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => explode("\n", $exception->getTraceAsString()),
            ],
        ]);
    }
};
