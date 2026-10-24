<?php

/**
 * Exposes a response factory via $deps['response'].
 *
 * Responses are immutable objects using PSR-7 method names
 * (getStatusCode, withStatus, getHeaders, withHeader, getBody, withBody...),
 * route callbacks can return them instead of printing the output.
 *
 * @see https://www.php-fig.org/psr/psr-7/
 * @see https://www.rfc-editor.org/rfc/rfc9457 (Problem Details)
 */
$deps['response'] = new class () {
    /**
     * Reason phrases of the most common status codes.
     *
     * @var array
     */
    private $phrases = [
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        409 => 'Conflict',
        410 => 'Gone',
        412 => 'Precondition Failed',
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
     * Create a response.
     *
     * @param string $body    Response body
     * @param int    $status  HTTP status code
     * @param array  $headers Header values indexed by name
     *
     * @return object
     */
    public function create(string $body = '', int $status = 200, array $headers = [])
    {
        return new class ($body, $status, $headers) {
            private $body;
            private $status;
            private $headers = [];

            public function __construct(string $body, int $status, array $headers)
            {
                $this->body = $body;
                $this->status = $status;

                foreach ($headers as $name => $value) {
                    $this->headers[strtolower($name)] = [$name, array_values((array) $value)];
                }
            }

            public function getStatusCode()
            {
                return $this->status;
            }

            public function withStatus(int $status)
            {
                $clone = clone $this;
                $clone->status = $status;

                return $clone;
            }

            /**
             * @return array Header values indexed by name
             */
            public function getHeaders()
            {
                $headers = [];

                foreach ($this->headers as $header) {
                    $headers[$header[0]] = $header[1];
                }

                return $headers;
            }

            public function hasHeader(string $name)
            {
                return isset($this->headers[strtolower($name)]);
            }

            /**
             * @return string[]
             */
            public function getHeader(string $name)
            {
                return $this->hasHeader($name) ? $this->headers[strtolower($name)][1] : [];
            }

            public function getHeaderLine(string $name)
            {
                return implode(', ', $this->getHeader($name));
            }

            /**
             * @param string|string[] $value
             */
            public function withHeader(string $name, $value)
            {
                $clone = clone $this;
                $clone->headers[strtolower($name)] = [$name, array_values((array) $value)];

                return $clone;
            }

            /**
             * @param string|string[] $value
             */
            public function withAddedHeader(string $name, $value)
            {
                return $this->withHeader($name, array_merge($this->getHeader($name), (array) $value));
            }

            public function withoutHeader(string $name)
            {
                $clone = clone $this;
                unset($clone->headers[strtolower($name)]);

                return $clone;
            }

            public function getBody()
            {
                return $this->body;
            }

            public function withBody(string $body)
            {
                $clone = clone $this;
                $clone->body = $body;

                return $clone;
            }

            /**
             * Set the Cache-Control header.
             *
             * @param int  $maxAge Seconds the response can be cached, 0 disables the cache
             * @param bool $public Whether shared caches can store the response
             */
            public function withCache(int $maxAge, bool $public = true)
            {
                if ($maxAge <= 0) {
                    return $this->withHeader('Cache-Control', 'no-store');
                }

                return $this->withHeader('Cache-Control', sprintf('%s, max-age=%d', $public ? 'public' : 'private', $maxAge));
            }

            public function withEtag(string $etag, bool $weak = false)
            {
                return $this->withHeader('ETag', ($weak ? 'W/' : '').'"'.trim($etag, '"').'"');
            }

            /**
             * @param int|DateTimeInterface $time
             */
            public function withLastModified($time)
            {
                $timestamp = $time instanceof DateTimeInterface ? $time->getTimestamp() : (int) $time;

                return $this->withHeader('Last-Modified', gmdate('D, d M Y H:i:s', $timestamp).' GMT');
            }

            /**
             * Decoded JSON body, useful in tests.
             *
             * @return mixed
             */
            public function json()
            {
                return json_decode($this->body, true);
            }

            public function __toString()
            {
                return $this->body;
            }
        };
    }

    /**
     * Create a JSON response.
     *
     * @param mixed $data    Value encoded as JSON
     * @param int   $status  HTTP status code
     * @param array $headers Additional headers
     * @param int   $flags   json_encode() flags
     *
     * @throws \InvalidArgumentException If the value can't be encoded
     *
     * @return object
     */
    public function json($data, int $status = 200, array $headers = [], int $flags = 0)
    {
        $body = json_encode($data, $flags | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (false === $body || JSON_ERROR_NONE !== json_last_error()) {
            throw new \InvalidArgumentException('Unable to encode JSON: '.json_last_error_msg());
        }

        return $this->create($body, $status, $headers + ['Content-Type' => 'application/json']);
    }

    public function text(string $text, int $status = 200, array $headers = [])
    {
        return $this->create($text, $status, $headers + ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function html(string $html, int $status = 200, array $headers = [])
    {
        return $this->create($html, $status, $headers + ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function noContent(array $headers = [])
    {
        return $this->create('', 204, $headers);
    }

    public function redirect(string $url, int $status = 302, array $headers = [])
    {
        return $this->create('', $status, $headers + ['Location' => $url]);
    }

    /**
     * Create a 201 Created response.
     *
     * @param string $location URL of the created resource
     * @param mixed  $data     Value encoded as JSON, null for an empty body
     */
    public function created(string $location, $data = null, array $headers = [])
    {
        $headers += ['Location' => $location];

        return is_null($data) ? $this->create('', 201, $headers) : $this->json($data, 201, $headers);
    }

    /**
     * Create a Problem Details response (RFC 9457).
     *
     * @param int         $status     HTTP status code
     * @param string|null $detail     Explanation specific to this occurrence of the problem
     * @param array       $extensions Additional members (type, title, instance, errors...)
     * @param array       $headers    Additional headers
     */
    public function problem(int $status, $detail = null, array $extensions = [], array $headers = [])
    {
        $problem = [
            'type' => 'about:blank',
            'title' => $this->reasonPhrase($status),
            'status' => $status,
        ];

        if (!is_null($detail) && '' !== $detail) {
            $problem['detail'] = $detail;
        }

        $problem = array_merge($problem, $extensions, ['status' => $status]);

        return $this->json($problem, $status, $headers + ['Content-Type' => 'application/problem+json']);
    }

    public function reasonPhrase(int $status)
    {
        return $this->phrases[$status] ?? 'Unknown Status';
    }

    /**
     * Whether a value can be sent as a response, PSR-7 responses included.
     */
    public function isResponse($value)
    {
        return is_object($value)
            && method_exists($value, 'getStatusCode')
            && method_exists($value, 'getHeaders')
            && method_exists($value, 'getBody')
            && method_exists($value, 'withBody');
    }

    /**
     * Middleware adding an ETag to successful GET/HEAD responses and replying
     * with 304 Not Modified when If-None-Match or If-Modified-Since match.
     *
     * @return callable
     */
    public function conditional(bool $weak = false)
    {
        return function ($request, callable $next) use ($weak) {
            $response = $next($request);

            if (!in_array($request->method(), ['GET', 'HEAD']) || 200 !== $response->getStatusCode()) {
                return $response;
            }

            if (!$response->hasHeader('ETag')) {
                $response = $response->withEtag(md5((string) $response->getBody()), $weak);
            }

            $etag = $response->getHeaderLine('ETag');
            $ifNoneMatch = $request->header('If-None-Match');
            $notModified = false;

            if (!is_null($ifNoneMatch)) {
                // weak comparison, as required for If-None-Match
                $normalize = function ($tag) {
                    return preg_replace('#^W/#', '', trim($tag));
                };
                $tags = array_map($normalize, explode(',', $ifNoneMatch));
                $notModified = in_array('*', $tags) || in_array($normalize($etag), $tags);
            } elseif (!is_null($since = $request->header('If-Modified-Since')) && $response->hasHeader('Last-Modified')) {
                $notModified = strtotime($response->getHeaderLine('Last-Modified')) <= strtotime($since);
            }

            if (!$notModified) {
                return $response;
            }

            $headers = [];

            foreach (['Cache-Control', 'Content-Location', 'Date', 'ETag', 'Expires', 'Last-Modified', 'Vary'] as $name) {
                if ($response->hasHeader($name)) {
                    $headers[$name] = $response->getHeader($name);
                }
            }

            return $this->create('', 304, $headers);
        };
    }
};
