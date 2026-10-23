<?php

/**
 * Exposes an object via $deps['http'] to integrate PSR-7 messages
 * and PSR-15 handlers and middlewares.
 *
 * Installed implementations (e.g. nyholm/psr7) are preferred, otherwise
 * a default implementation is used, with interfaces polyfilled when missing.
 *
 * @see https://www.php-fig.org/psr/psr-7/
 * @see https://www.php-fig.org/psr/psr-15/
 * @see https://www.php-fig.org/psr/psr-17/
 */
$deps['http'] = new class () {
    /**
     * PSR-17 factory used to create messages.
     *
     * @var object|null
     */
    private $factory;

    /**
     * Request created from globals, shared by every route.
     *
     * @var \Psr\Http\Message\ServerRequestInterface|null
     */
    private $request;

    /**
     * Known PSR-17 factories implementing every needed interface.
     *
     * @var string[]
     */
    private $knownFactories = [
        'Nyholm\Psr7\Factory\Psr17Factory',
        'GuzzleHttp\Psr7\HttpFactory',
    ];

    /**
     * Set the PSR-17 factory, it has to implement the server request,
     * URI, stream and uploaded file factory interfaces.
     *
     * @param  object                    $factory
     * @throws \InvalidArgumentException If the factory is missing required methods
     */
    public function setFactory($factory)
    {
        foreach (['createServerRequest', 'createUri', 'createStream', 'createStreamFromFile', 'createUploadedFile'] as $method) {
            if (!is_object($factory) || !method_exists($factory, $method)) {
                throw new \InvalidArgumentException(sprintf('PSR-17 factory is missing method: %s', $method));
            }
        }

        $this->factory = $factory;
    }

    /**
     * Get the PSR-17 factory: when not defined, a known implementation is used
     * if installed, otherwise the default one.
     *
     * @throws \RuntimeException If no factory is available
     * @return object
     */
    public function getFactory()
    {
        if (is_null($this->factory)) {
            foreach ($this->knownFactories as $fqcn) {
                if (class_exists($fqcn)) {
                    $this->factory = new $fqcn();

                    break;
                }
            }
        }

        if (is_null($this->factory)) {
            $this->factory = $this->createDefaultFactory();
        }

        if (is_null($this->factory)) {
            throw new \RuntimeException('No PSR-17 factory available for psr/http-message 2.x: install one (e.g. nyholm/psr7) or call setFactory()');
        }

        return $this->factory;
    }

    /**
     * Override the request passed to routes.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     */
    public function setRequest(\Psr\Http\Message\ServerRequestInterface $request)
    {
        $this->request = $request;
    }

    /**
     * Get the current request, created from globals the first time.
     *
     * @return \Psr\Http\Message\ServerRequestInterface
     */
    public function getRequest()
    {
        if (is_null($this->request)) {
            $this->request = $this->createRequestFromGlobals();
        }

        return $this->request;
    }

    /**
     * Create a PSR-15 request handler from a list of middlewares,
     * the last argument is the handler that creates the response.
     *
     * Middlewares can be MiddlewareInterface instances or callables
     * with the same signature: function ($request, $handler).
     * Handlers can be RequestHandlerInterface instances or callables
     * with the signature: function ($request).
     *
     * @param  mixed                                    ...$stack
     * @return \Psr\Http\Server\RequestHandlerInterface
     */
    public function pipe(...$stack)
    {
        $handler = array_pop($stack);

        return new class ($stack, $handler) implements \Psr\Http\Server\RequestHandlerInterface {
            /**
             * @var array
             */
            private $middlewares;

            /**
             * @var mixed
             */
            private $handler;

            public function __construct(array $middlewares, $handler)
            {
                $this->middlewares = $middlewares;
                $this->handler = $handler;
            }

            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                if (empty($this->middlewares)) {
                    return $this->handler instanceof \Psr\Http\Server\RequestHandlerInterface
                        ? $this->handler->handle($request)
                        : call_user_func($this->handler, $request);
                }

                $middlewares = $this->middlewares;
                $middleware = array_shift($middlewares);
                $next = new self($middlewares, $this->handler);

                return $middleware instanceof \Psr\Http\Server\MiddlewareInterface
                    ? $middleware->process($request, $next)
                    : call_user_func($middleware, $request, $next);
            }
        };
    }

    /**
     * Send status, headers and body of a response.
     *
     * When headers are already sent (e.g. multiple actions on the same route)
     * only the body is sent.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     */
    public function emit(\Psr\Http\Message\ResponseInterface $response)
    {
        if (!headers_sent()) {
            $status = $response->getStatusCode();

            header(rtrim(sprintf(
                'HTTP/%s %d %s',
                $response->getProtocolVersion(),
                $status,
                $response->getReasonPhrase()
            )), true, $status);

            foreach ($response->getHeaders() as $name => $values) {
                // multiple cookies can't be merged in a single header
                $replace = 'set-cookie' !== strtolower($name);

                foreach ($values as $value) {
                    header($name.': '.$value, $replace);
                    $replace = false;
                }
            }
        }

        $body = $response->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        while (!$body->eof()) {
            echo $body->read(8192);
        }
    }

    /**
     * Convert a PSR-15 request handler into a route callback.
     *
     * @param  mixed $cb
     * @return mixed
     */
    public function toCallable($cb)
    {
        if ($cb instanceof \Psr\Http\Server\RequestHandlerInterface) {
            return function (\Psr\Http\Message\ServerRequestInterface $request) use ($cb) {
                return $cb->handle($request);
            };
        }

        return $cb;
    }

    /**
     * Wrap a route callback to emit the returned PSR-7 response, if any.
     *
     * @param  callable $cb
     * @return \Closure
     */
    public function responder(callable $cb)
    {
        return function () use ($cb) {
            $response = call_user_func_array($cb, func_get_args());

            if ($response instanceof \Psr\Http\Message\ResponseInterface) {
                $this->emit($response);
            }

            return $response;
        };
    }

    /**
     * Whether a route callback requires the PSR-7 request.
     *
     * @param  \ReflectionFunctionAbstract $reflector
     * @return bool
     */
    public function wantsRequest(\ReflectionFunctionAbstract $reflector)
    {
        foreach ($reflector->getParameters() as $parameter) {
            if ($this->isRequestParameter($parameter)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve route callback arguments: the request is injected by type,
     * named subpatterns by name (and added as request attributes),
     * other subpatterns by position.
     *
     * @param  \ReflectionFunctionAbstract $reflector
     * @param  array                       $submatches Matches as returned by preg_match
     * @return array
     */
    public function arguments(\ReflectionFunctionAbstract $reflector, array $submatches)
    {
        $request = $this->getRequest();

        // named subpatterns are followed by a numeric duplicate
        $values = [];
        $name = null;

        foreach ($submatches as $key => $value) {
            if (0 === $key) {
                continue;
            }

            if (is_string($key)) {
                $name = $key;

                continue;
            }

            $values[] = [$name, $value];

            if (!is_null($name)) {
                $request = $request->withAttribute($name, $value);
                $name = null;
            }
        }

        $parameters = $reflector->getParameters();
        $args = [];

        foreach ($parameters as $i => $parameter) {
            if ($this->isRequestParameter($parameter)) {
                $args[$i] = $request;

                continue;
            }

            foreach ($values as $j => $value) {
                if ($value[0] === $parameter->name) {
                    $args[$i] = $value[1];
                    unset($values[$j]);

                    break;
                }
            }
        }

        foreach ($parameters as $i => $parameter) {
            if (array_key_exists($i, $args)) {
                continue;
            }

            if ($parameter->isVariadic()) {
                foreach ($values as $value) {
                    $args[] = $value[1];
                }

                break;
            }

            if (!empty($values)) {
                $value = array_shift($values);
                $args[$i] = $value[1];

                continue;
            }

            $args[$i] = $parameter->isDefaultValueAvailable()
                ? $parameter->getDefaultValue()
                : null;
        }

        ksort($args);

        return array_values($args);
    }

    /**
     * @param  \ReflectionParameter $parameter
     * @return bool
     */
    private function isRequestParameter(\ReflectionParameter $parameter)
    {
        $type = $parameter->getType();

        if (is_null($type)) {
            return false;
        }

        // union and intersection types aren't supported
        $type = method_exists($type, 'getName')
            ? $type->getName()
            : (string) $type;

        $type = ltrim($type, '?\\');

        // checked by name first, so PSR interfaces are loaded only when needed
        return 0 === strpos($type, 'Psr\\Http\\Message\\')
            && is_a('Psr\Http\Message\ServerRequestInterface', $type, true);
    }

    /**
     * @return \Psr\Http\Message\ServerRequestInterface
     */
    private function createRequestFromGlobals()
    {
        $factory = $this->getFactory();
        $server = $_SERVER;

        $https = !empty($server['HTTPS']) && 'off' !== strtolower($server['HTTPS']);
        $host = $server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? 'localhost';

        if (empty($server['HTTP_HOST']) && !empty($server['SERVER_PORT']) && !in_array((int) $server['SERVER_PORT'], [80, 443], true)) {
            $host .= ':'.$server['SERVER_PORT'];
        }

        $uri = $factory->createUri(($https ? 'https' : 'http').'://'.$host.($server['REQUEST_URI'] ?? '/'));

        $request = $factory->createServerRequest($server['REQUEST_METHOD'] ?? 'GET', $uri, $server)
            ->withCookieParams($_COOKIE)
            ->withQueryParams($_GET)
            ->withUploadedFiles($this->normalizeFiles($_FILES))
            ->withBody($factory->createStreamFromFile('php://input', 'r'));

        if (!empty($server['SERVER_PROTOCOL']) && preg_match('#^HTTP/(\d(?:\.\d)?)$#', $server['SERVER_PROTOCOL'], $protocol)) {
            $request = $request->withProtocolVersion($protocol[1]);
        }

        if (!empty($_POST)) {
            $request = $request->withParsedBody($_POST);
        }

        foreach ($server as $key => $value) {
            if (0 === strpos($key, 'HTTP_')) {
                $key = substr($key, 5);
            } elseif (!in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                continue;
            }

            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));
            $request = $request->withHeader($name, (string) $value);
        }

        return $request;
    }

    /**
     * Convert $_FILES into a tree of UploadedFileInterface instances.
     *
     * @param  array $files
     * @return array
     */
    private function normalizeFiles(array $files)
    {
        $normalized = [];

        foreach ($files as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            $normalized[$key] = isset($value['tmp_name'])
                ? $this->createUploadedFiles(
                    $value['tmp_name'],
                    $value['size'] ?? null,
                    $value['error'] ?? UPLOAD_ERR_OK,
                    $value['name'] ?? null,
                    $value['type'] ?? null
                )
                : $this->normalizeFiles($value);
        }

        return $normalized;
    }

    /**
     * @return mixed UploadedFileInterface or nested arrays of them
     */
    private function createUploadedFiles($tmpName, $size, $error, $name, $type)
    {
        if (is_array($tmpName)) {
            $files = [];

            foreach ($tmpName as $key => $value) {
                $files[$key] = $this->createUploadedFiles(
                    $value,
                    $size[$key] ?? null,
                    $error[$key] ?? UPLOAD_ERR_OK,
                    $name[$key] ?? null,
                    $type[$key] ?? null
                );
            }

            return $files;
        }

        $factory = $this->getFactory();
        $stream = UPLOAD_ERR_OK === (int) $error
            ? $factory->createStreamFromFile($tmpName, 'r')
            : $factory->createStream();

        return $factory->createUploadedFile(
            $stream,
            is_null($size) ? null : (int) $size,
            (int) $error,
            $name,
            $type
        );
    }

    /**
     * Default PSR-17 factory, creating PSR-7 messages based on anonymous classes.
     *
     * Messages follow psr/http-message 1.x signatures (polyfilled when missing),
     * so the factory isn't available when psr/http-message 2.x is installed.
     *
     * @return object|null
     */
    private function createDefaultFactory()
    {
        $method = new \ReflectionMethod('Psr\Http\Message\MessageInterface', 'getProtocolVersion');

        if ($method->hasReturnType()) {
            return null;
        }

        return new class () {
            /**
             * @param  string                                $method
             * @param  \Psr\Http\Message\UriInterface|string $uri
             * @return \Psr\Http\Message\RequestInterface
             */
            public function createRequest($method, $uri)
            {
                return $this->createServerRequest($method, $uri);
            }

            /**
             * @param  string                                   $method
             * @param  \Psr\Http\Message\UriInterface|string    $uri
             * @param  array                                    $serverParams
             * @return \Psr\Http\Message\ServerRequestInterface
             */
            public function createServerRequest($method, $uri, array $serverParams = [])
            {
                if (!($uri instanceof \Psr\Http\Message\UriInterface)) {
                    $uri = $this->createUri($uri);
                }

                return new class ($this, $method, $uri, $serverParams) implements \Psr\Http\Message\ServerRequestInterface {
                    private $factory;
                    private $protocol = '1.1';
                    private $headers = [];
                    private $headerNames = [];
                    private $body;
                    private $method;
                    private $requestTarget;
                    private $uri;
                    private $serverParams;
                    private $cookieParams = [];
                    private $queryParams = [];
                    private $uploadedFiles = [];
                    private $parsedBody;
                    private $attributes = [];

                    public function __construct($factory, $method, \Psr\Http\Message\UriInterface $uri, array $serverParams)
                    {
                        $this->factory = $factory;
                        $this->method = $this->filterMethod($method);
                        $this->uri = $uri;
                        $this->serverParams = $serverParams;
                        $this->updateHostFromUri();
                    }

                    public function getProtocolVersion()
                    {
                        return $this->protocol;
                    }

                    public function withProtocolVersion($version)
                    {
                        if (!is_string($version) || !preg_match('#^\d+(?:\.\d+)?$#', $version)) {
                            throw new \InvalidArgumentException('Invalid HTTP protocol version');
                        }

                        $new = clone $this;
                        $new->protocol = $version;

                        return $new;
                    }

                    public function getHeaders()
                    {
                        return $this->headers;
                    }

                    public function hasHeader($name)
                    {
                        return isset($this->headerNames[strtolower($name)]);
                    }

                    public function getHeader($name)
                    {
                        $name = strtolower($name);

                        return isset($this->headerNames[$name])
                            ? $this->headers[$this->headerNames[$name]]
                            : [];
                    }

                    public function getHeaderLine($name)
                    {
                        return implode(', ', $this->getHeader($name));
                    }

                    public function withHeader($name, $value)
                    {
                        $value = $this->filterHeader($name, $value);
                        $new = $this->withoutHeader($name);
                        $new->headerNames[strtolower($name)] = $name;
                        $new->headers[$name] = $value;

                        return $new;
                    }

                    public function withAddedHeader($name, $value)
                    {
                        $value = $this->filterHeader($name, $value);
                        $new = clone $this;

                        if (isset($new->headerNames[strtolower($name)])) {
                            $name = $new->headerNames[strtolower($name)];
                            $value = array_merge($new->headers[$name], $value);
                        }

                        $new->headerNames[strtolower($name)] = $name;
                        $new->headers[$name] = $value;

                        return $new;
                    }

                    public function withoutHeader($name)
                    {
                        $new = clone $this;

                        if (isset($new->headerNames[strtolower($name)])) {
                            unset($new->headers[$new->headerNames[strtolower($name)]], $new->headerNames[strtolower($name)]);
                        }

                        return $new;
                    }

                    public function getBody()
                    {
                        if (is_null($this->body)) {
                            $this->body = $this->factory->createStream();
                        }

                        return $this->body;
                    }

                    public function withBody(\Psr\Http\Message\StreamInterface $body)
                    {
                        $new = clone $this;
                        $new->body = $body;

                        return $new;
                    }

                    public function getRequestTarget()
                    {
                        if (!is_null($this->requestTarget)) {
                            return $this->requestTarget;
                        }

                        $target = $this->uri->getPath();

                        if ('' === $target) {
                            $target = '/';
                        }

                        if ('' !== $this->uri->getQuery()) {
                            $target .= '?'.$this->uri->getQuery();
                        }

                        return $target;
                    }

                    public function withRequestTarget($requestTarget)
                    {
                        if (!is_string($requestTarget) || preg_match('#\s#', $requestTarget)) {
                            throw new \InvalidArgumentException('Invalid request target provided, cannot contain whitespace');
                        }

                        $new = clone $this;
                        $new->requestTarget = $requestTarget;

                        return $new;
                    }

                    public function getMethod()
                    {
                        return $this->method;
                    }

                    public function withMethod($method)
                    {
                        $new = clone $this;
                        $new->method = $this->filterMethod($method);

                        return $new;
                    }

                    public function getUri()
                    {
                        return $this->uri;
                    }

                    public function withUri(\Psr\Http\Message\UriInterface $uri, $preserveHost = false)
                    {
                        $new = clone $this;
                        $new->uri = $uri;

                        if (!$preserveHost || !$new->hasHeader('Host')) {
                            $new->updateHostFromUri();
                        }

                        return $new;
                    }

                    public function getServerParams()
                    {
                        return $this->serverParams;
                    }

                    public function getCookieParams()
                    {
                        return $this->cookieParams;
                    }

                    public function withCookieParams(array $cookies)
                    {
                        $new = clone $this;
                        $new->cookieParams = $cookies;

                        return $new;
                    }

                    public function getQueryParams()
                    {
                        return $this->queryParams;
                    }

                    public function withQueryParams(array $query)
                    {
                        $new = clone $this;
                        $new->queryParams = $query;

                        return $new;
                    }

                    public function getUploadedFiles()
                    {
                        return $this->uploadedFiles;
                    }

                    public function withUploadedFiles(array $uploadedFiles)
                    {
                        array_walk_recursive($uploadedFiles, function ($file) {
                            if (!($file instanceof \Psr\Http\Message\UploadedFileInterface)) {
                                throw new \InvalidArgumentException('Uploaded files must be instances of UploadedFileInterface');
                            }
                        });

                        $new = clone $this;
                        $new->uploadedFiles = $uploadedFiles;

                        return $new;
                    }

                    public function getParsedBody()
                    {
                        return $this->parsedBody;
                    }

                    public function withParsedBody($data)
                    {
                        if (!is_null($data) && !is_array($data) && !is_object($data)) {
                            throw new \InvalidArgumentException('Parsed body must be null, an array or an object');
                        }

                        $new = clone $this;
                        $new->parsedBody = $data;

                        return $new;
                    }

                    public function getAttributes()
                    {
                        return $this->attributes;
                    }

                    public function getAttribute($name, $default = null)
                    {
                        return array_key_exists($name, $this->attributes)
                            ? $this->attributes[$name]
                            : $default;
                    }

                    public function withAttribute($name, $value)
                    {
                        $new = clone $this;
                        $new->attributes[$name] = $value;

                        return $new;
                    }

                    public function withoutAttribute($name)
                    {
                        $new = clone $this;
                        unset($new->attributes[$name]);

                        return $new;
                    }

                    private function filterMethod($method)
                    {
                        if (!is_string($method) || !preg_match('/^[!#$%&\'*+.^_`|~0-9a-zA-Z-]+$/', $method)) {
                            throw new \InvalidArgumentException('Invalid HTTP method');
                        }

                        return $method;
                    }

                    private function filterHeader($name, $value)
                    {
                        if (!is_string($name) || !preg_match('/^[!#$%&\'*+.^_`|~0-9a-zA-Z-]+$/', $name)) {
                            throw new \InvalidArgumentException('Header name must be an RFC 7230 compatible string');
                        }

                        $values = is_array($value) ? array_values($value) : [$value];

                        if (empty($values)) {
                            throw new \InvalidArgumentException('Header values can\'t be an empty array');
                        }

                        foreach ($values as $i => $value) {
                            if ((!is_string($value) && !is_numeric($value)) || !preg_match('/^[ \t\x21-\x7E\x80-\xFF]*$/', (string) $value)) {
                                throw new \InvalidArgumentException('Header values must be RFC 7230 compatible strings');
                            }

                            $values[$i] = trim((string) $value, " \t");
                        }

                        return $values;
                    }

                    private function updateHostFromUri()
                    {
                        $host = $this->uri->getHost();

                        if ('' === $host) {
                            return;
                        }

                        if (!is_null($this->uri->getPort())) {
                            $host .= ':'.$this->uri->getPort();
                        }

                        $name = $this->headerNames['host'] ?? 'Host';
                        $this->headerNames['host'] = $name;

                        // the Host header has to be the first one
                        $this->headers = [$name => [$host]] + $this->headers;
                    }
                };
            }

            /**
             * @param  int                                 $code
             * @param  string                              $reasonPhrase
             * @return \Psr\Http\Message\ResponseInterface
             */
            public function createResponse($code = 200, $reasonPhrase = '')
            {
                return new class ($this, $code, $reasonPhrase) implements \Psr\Http\Message\ResponseInterface {
                    private $factory;
                    private $protocol = '1.1';
                    private $headers = [];
                    private $headerNames = [];
                    private $body;
                    private $statusCode;
                    private $reasonPhrase;
                    private $phrases = [
                        100 => 'Continue', 101 => 'Switching Protocols', 102 => 'Processing', 103 => 'Early Hints',
                        200 => 'OK', 201 => 'Created', 202 => 'Accepted', 203 => 'Non-Authoritative Information',
                        204 => 'No Content', 205 => 'Reset Content', 206 => 'Partial Content', 207 => 'Multi-Status',
                        208 => 'Already Reported', 226 => 'IM Used',
                        300 => 'Multiple Choices', 301 => 'Moved Permanently', 302 => 'Found', 303 => 'See Other',
                        304 => 'Not Modified', 305 => 'Use Proxy', 307 => 'Temporary Redirect', 308 => 'Permanent Redirect',
                        400 => 'Bad Request', 401 => 'Unauthorized', 402 => 'Payment Required', 403 => 'Forbidden',
                        404 => 'Not Found', 405 => 'Method Not Allowed', 406 => 'Not Acceptable',
                        407 => 'Proxy Authentication Required', 408 => 'Request Timeout', 409 => 'Conflict',
                        410 => 'Gone', 411 => 'Length Required', 412 => 'Precondition Failed', 413 => 'Content Too Large',
                        414 => 'URI Too Long', 415 => 'Unsupported Media Type', 416 => 'Range Not Satisfiable',
                        417 => 'Expectation Failed', 418 => 'I\'m a teapot', 421 => 'Misdirected Request',
                        422 => 'Unprocessable Content', 423 => 'Locked', 424 => 'Failed Dependency', 425 => 'Too Early',
                        426 => 'Upgrade Required', 428 => 'Precondition Required', 429 => 'Too Many Requests',
                        431 => 'Request Header Fields Too Large', 451 => 'Unavailable For Legal Reasons',
                        500 => 'Internal Server Error', 501 => 'Not Implemented', 502 => 'Bad Gateway',
                        503 => 'Service Unavailable', 504 => 'Gateway Timeout', 505 => 'HTTP Version Not Supported',
                        506 => 'Variant Also Negotiates', 507 => 'Insufficient Storage', 508 => 'Loop Detected',
                        510 => 'Not Extended', 511 => 'Network Authentication Required',
                    ];

                    public function __construct($factory, $code, $reasonPhrase)
                    {
                        $this->factory = $factory;
                        $this->setStatus($code, $reasonPhrase);
                    }

                    public function getProtocolVersion()
                    {
                        return $this->protocol;
                    }

                    public function withProtocolVersion($version)
                    {
                        if (!is_string($version) || !preg_match('#^\d+(?:\.\d+)?$#', $version)) {
                            throw new \InvalidArgumentException('Invalid HTTP protocol version');
                        }

                        $new = clone $this;
                        $new->protocol = $version;

                        return $new;
                    }

                    public function getHeaders()
                    {
                        return $this->headers;
                    }

                    public function hasHeader($name)
                    {
                        return isset($this->headerNames[strtolower($name)]);
                    }

                    public function getHeader($name)
                    {
                        $name = strtolower($name);

                        return isset($this->headerNames[$name])
                            ? $this->headers[$this->headerNames[$name]]
                            : [];
                    }

                    public function getHeaderLine($name)
                    {
                        return implode(', ', $this->getHeader($name));
                    }

                    public function withHeader($name, $value)
                    {
                        $value = $this->filterHeader($name, $value);
                        $new = $this->withoutHeader($name);
                        $new->headerNames[strtolower($name)] = $name;
                        $new->headers[$name] = $value;

                        return $new;
                    }

                    public function withAddedHeader($name, $value)
                    {
                        $value = $this->filterHeader($name, $value);
                        $new = clone $this;

                        if (isset($new->headerNames[strtolower($name)])) {
                            $name = $new->headerNames[strtolower($name)];
                            $value = array_merge($new->headers[$name], $value);
                        }

                        $new->headerNames[strtolower($name)] = $name;
                        $new->headers[$name] = $value;

                        return $new;
                    }

                    public function withoutHeader($name)
                    {
                        $new = clone $this;

                        if (isset($new->headerNames[strtolower($name)])) {
                            unset($new->headers[$new->headerNames[strtolower($name)]], $new->headerNames[strtolower($name)]);
                        }

                        return $new;
                    }

                    public function getBody()
                    {
                        if (is_null($this->body)) {
                            $this->body = $this->factory->createStream();
                        }

                        return $this->body;
                    }

                    public function withBody(\Psr\Http\Message\StreamInterface $body)
                    {
                        $new = clone $this;
                        $new->body = $body;

                        return $new;
                    }

                    public function getStatusCode()
                    {
                        return $this->statusCode;
                    }

                    public function withStatus($code, $reasonPhrase = '')
                    {
                        $new = clone $this;
                        $new->setStatus($code, $reasonPhrase);

                        return $new;
                    }

                    public function getReasonPhrase()
                    {
                        return $this->reasonPhrase;
                    }

                    private function setStatus($code, $reasonPhrase)
                    {
                        if (!is_int($code) || $code < 100 || $code > 599) {
                            throw new \InvalidArgumentException('Status code has to be an integer between 100 and 599');
                        }

                        if (!is_string($reasonPhrase)) {
                            throw new \InvalidArgumentException('Reason phrase must be a string');
                        }

                        $this->statusCode = $code;
                        $this->reasonPhrase = '' === $reasonPhrase && isset($this->phrases[$code])
                            ? $this->phrases[$code]
                            : $reasonPhrase;
                    }

                    private function filterHeader($name, $value)
                    {
                        if (!is_string($name) || !preg_match('/^[!#$%&\'*+.^_`|~0-9a-zA-Z-]+$/', $name)) {
                            throw new \InvalidArgumentException('Header name must be an RFC 7230 compatible string');
                        }

                        $values = is_array($value) ? array_values($value) : [$value];

                        if (empty($values)) {
                            throw new \InvalidArgumentException('Header values can\'t be an empty array');
                        }

                        foreach ($values as $i => $value) {
                            if ((!is_string($value) && !is_numeric($value)) || !preg_match('/^[ \t\x21-\x7E\x80-\xFF]*$/', (string) $value)) {
                                throw new \InvalidArgumentException('Header values must be RFC 7230 compatible strings');
                            }

                            $values[$i] = trim((string) $value, " \t");
                        }

                        return $values;
                    }
                };
            }

            /**
             * @param  string                            $content
             * @return \Psr\Http\Message\StreamInterface
             */
            public function createStream($content = '')
            {
                $resource = fopen('php://temp', 'r+');
                fwrite($resource, (string) $content);
                rewind($resource);

                return $this->createStreamFromResource($resource);
            }

            /**
             * @param  string                            $filename
             * @param  string                            $mode
             * @throws \InvalidArgumentException         If the mode is invalid
             * @throws \RuntimeException                 If the file can't be opened
             * @return \Psr\Http\Message\StreamInterface
             */
            public function createStreamFromFile($filename, $mode = 'r')
            {
                if (!preg_match('#^[rwaxc][bte+]*$#', $mode)) {
                    throw new \InvalidArgumentException(sprintf('Invalid file opening mode "%s"', $mode));
                }

                $resource = '' === $filename ? false : @fopen($filename, $mode);

                if (false === $resource) {
                    throw new \RuntimeException(sprintf('Unable to open "%s" using mode "%s"', $filename, $mode));
                }

                return $this->createStreamFromResource($resource);
            }

            /**
             * @param  resource                          $resource
             * @return \Psr\Http\Message\StreamInterface
             */
            public function createStreamFromResource($resource)
            {
                return new class ($resource) implements \Psr\Http\Message\StreamInterface {
                    private $resource;
                    private $uri;
                    private $seekable = false;
                    private $readable = false;
                    private $writable = false;

                    public function __construct($resource)
                    {
                        if (!is_resource($resource) || 'stream' !== get_resource_type($resource)) {
                            throw new \InvalidArgumentException('Stream must be a resource');
                        }

                        $meta = stream_get_meta_data($resource);

                        $this->resource = $resource;
                        $this->uri = $meta['uri'] ?? null;
                        $this->seekable = $meta['seekable'];
                        $this->readable = false !== strpbrk($meta['mode'], 'r+');
                        $this->writable = false !== strpbrk($meta['mode'], 'waxc+');
                    }

                    public function __destruct()
                    {
                        $this->close();
                    }

                    public function __toString()
                    {
                        try {
                            if ($this->isSeekable()) {
                                $this->seek(0);
                            }

                            return $this->getContents();
                        } catch (\Exception $e) {
                            return '';
                        }
                    }

                    public function close()
                    {
                        if (is_resource($this->resource)) {
                            fclose($this->resource);
                        }

                        $this->detach();
                    }

                    public function detach()
                    {
                        $resource = $this->resource;

                        $this->resource = null;
                        $this->uri = null;
                        $this->seekable = $this->readable = $this->writable = false;

                        return $resource;
                    }

                    public function getSize()
                    {
                        if (is_null($this->resource)) {
                            return null;
                        }

                        if (is_string($this->uri) && '' !== $this->uri) {
                            clearstatcache(true, $this->uri);
                        }

                        $stats = fstat($this->resource);

                        return $stats['size'] ?? null;
                    }

                    public function tell()
                    {
                        $position = ftell($this->attached());

                        if (false === $position) {
                            throw new \RuntimeException('Unable to determine stream position');
                        }

                        return $position;
                    }

                    public function eof()
                    {
                        return is_null($this->resource) || feof($this->resource);
                    }

                    public function isSeekable()
                    {
                        return $this->seekable;
                    }

                    public function seek($offset, $whence = SEEK_SET)
                    {
                        $resource = $this->attached();

                        if (!$this->seekable) {
                            throw new \RuntimeException('Stream is not seekable');
                        }

                        if (-1 === fseek($resource, $offset, $whence)) {
                            throw new \RuntimeException(sprintf('Unable to seek to stream position %s with whence %s', $offset, $whence));
                        }
                    }

                    public function rewind()
                    {
                        $this->seek(0);
                    }

                    public function isWritable()
                    {
                        return $this->writable;
                    }

                    public function write($string)
                    {
                        $resource = $this->attached();

                        if (!$this->writable) {
                            throw new \RuntimeException('Cannot write to a non-writable stream');
                        }

                        $result = fwrite($resource, $string);

                        if (false === $result) {
                            throw new \RuntimeException('Unable to write to stream');
                        }

                        return $result;
                    }

                    public function isReadable()
                    {
                        return $this->readable;
                    }

                    public function read($length)
                    {
                        $resource = $this->attached();

                        if (!$this->readable) {
                            throw new \RuntimeException('Cannot read from non-readable stream');
                        }

                        if ($length < 0) {
                            throw new \RuntimeException('Length parameter cannot be negative');
                        }

                        if (0 === $length) {
                            return '';
                        }

                        $result = fread($resource, $length);

                        if (false === $result) {
                            throw new \RuntimeException('Unable to read from stream');
                        }

                        return $result;
                    }

                    public function getContents()
                    {
                        $resource = $this->attached();

                        if (!$this->readable) {
                            throw new \RuntimeException('Cannot read from non-readable stream');
                        }

                        $contents = stream_get_contents($resource);

                        if (false === $contents) {
                            throw new \RuntimeException('Unable to read stream contents');
                        }

                        return $contents;
                    }

                    public function getMetadata($key = null)
                    {
                        if (is_null($this->resource)) {
                            return is_null($key) ? [] : null;
                        }

                        $meta = stream_get_meta_data($this->resource);

                        if (is_null($key)) {
                            return $meta;
                        }

                        return $meta[$key] ?? null;
                    }

                    private function attached()
                    {
                        if (is_null($this->resource)) {
                            throw new \RuntimeException('Stream is detached');
                        }

                        return $this->resource;
                    }
                };
            }

            /**
             * @param  string                         $uri
             * @throws \InvalidArgumentException      If the URI can't be parsed
             * @return \Psr\Http\Message\UriInterface
             */
            public function createUri($uri = '')
            {
                return new class ($uri) implements \Psr\Http\Message\UriInterface {
                    private $scheme = '';
                    private $userInfo = '';
                    private $host = '';
                    private $port;
                    private $path = '';
                    private $query = '';
                    private $fragment = '';
                    private $defaultPorts = [
                        'http' => 80,
                        'https' => 443,
                        'ws' => 80,
                        'wss' => 443,
                        'ftp' => 21,
                    ];

                    public function __construct($uri)
                    {
                        if (!is_string($uri)) {
                            throw new \InvalidArgumentException('URI must be a string');
                        }

                        if ('' === $uri) {
                            return;
                        }

                        $parts = parse_url($uri);

                        if (false === $parts) {
                            throw new \InvalidArgumentException(sprintf('Unable to parse URI: "%s"', $uri));
                        }

                        $this->scheme = strtolower($parts['scheme'] ?? '');
                        $this->userInfo = $this->encode($parts['user'] ?? '', '');
                        $this->host = strtolower($parts['host'] ?? '');
                        $this->port = isset($parts['port']) ? $this->filterPort($parts['port']) : null;
                        $this->path = $this->encode($parts['path'] ?? '', ':@\/');
                        $this->query = $this->encode($parts['query'] ?? '', ':@\/\?');
                        $this->fragment = $this->encode($parts['fragment'] ?? '', ':@\/\?');

                        if (isset($parts['pass'])) {
                            $this->userInfo .= ':'.$this->encode($parts['pass'], '');
                        }
                    }

                    public function getScheme()
                    {
                        return $this->scheme;
                    }

                    public function getAuthority()
                    {
                        if ('' === $this->host) {
                            return '';
                        }

                        $authority = $this->host;

                        if ('' !== $this->userInfo) {
                            $authority = $this->userInfo.'@'.$authority;
                        }

                        if (!is_null($this->getPort())) {
                            $authority .= ':'.$this->port;
                        }

                        return $authority;
                    }

                    public function getUserInfo()
                    {
                        return $this->userInfo;
                    }

                    public function getHost()
                    {
                        return $this->host;
                    }

                    public function getPort()
                    {
                        if (isset($this->defaultPorts[$this->scheme]) && $this->defaultPorts[$this->scheme] === $this->port) {
                            return null;
                        }

                        return $this->port;
                    }

                    public function getPath()
                    {
                        return $this->path;
                    }

                    public function getQuery()
                    {
                        return $this->query;
                    }

                    public function getFragment()
                    {
                        return $this->fragment;
                    }

                    public function withScheme($scheme)
                    {
                        $new = clone $this;
                        $new->scheme = strtolower($this->filterString($scheme, 'Scheme'));

                        return $new;
                    }

                    public function withUserInfo($user, $password = null)
                    {
                        $new = clone $this;
                        $new->userInfo = $this->encode($this->filterString($user, 'User'), '');

                        if (!is_null($password) && '' !== $password) {
                            $new->userInfo .= ':'.$this->encode($this->filterString($password, 'Password'), '');
                        }

                        return $new;
                    }

                    public function withHost($host)
                    {
                        $new = clone $this;
                        $new->host = strtolower($this->filterString($host, 'Host'));

                        return $new;
                    }

                    public function withPort($port)
                    {
                        $new = clone $this;
                        $new->port = is_null($port) ? null : $this->filterPort($port);

                        return $new;
                    }

                    public function withPath($path)
                    {
                        $new = clone $this;
                        $new->path = $this->encode($this->filterString($path, 'Path'), ':@\/');

                        return $new;
                    }

                    public function withQuery($query)
                    {
                        $new = clone $this;
                        $new->query = $this->encode($this->filterString($query, 'Query'), ':@\/\?');

                        return $new;
                    }

                    public function withFragment($fragment)
                    {
                        $new = clone $this;
                        $new->fragment = $this->encode($this->filterString($fragment, 'Fragment'), ':@\/\?');

                        return $new;
                    }

                    public function __toString()
                    {
                        $uri = '';

                        if ('' !== $this->scheme) {
                            $uri .= $this->scheme.':';
                        }

                        $authority = $this->getAuthority();

                        if ('' !== $authority) {
                            $uri .= '//'.$authority;
                        }

                        $path = $this->path;

                        if ('' !== $path && '/' !== $path[0] && '' !== $authority) {
                            // a path has to be absolute when there's an authority
                            $path = '/'.$path;
                        } elseif ('' === $authority && 0 === strpos($path, '//')) {
                            // a path can't start with "//" without an authority
                            $path = '/'.ltrim($path, '/');
                        }

                        $uri .= $path;

                        if ('' !== $this->query) {
                            $uri .= '?'.$this->query;
                        }

                        if ('' !== $this->fragment) {
                            $uri .= '#'.$this->fragment;
                        }

                        return $uri;
                    }

                    private function filterString($value, $name)
                    {
                        if (!is_string($value)) {
                            throw new \InvalidArgumentException(sprintf('%s must be a string', $name));
                        }

                        return $value;
                    }

                    private function filterPort($port)
                    {
                        if ((!is_int($port) && !ctype_digit((string) $port)) || $port < 0 || $port > 65535) {
                            throw new \InvalidArgumentException(sprintf('Invalid port: %s, it must be between 0 and 65535', $port));
                        }

                        return (int) $port;
                    }

                    /**
                     * Percent-encode characters not allowed, without double encoding.
                     */
                    private function encode($value, $allowed)
                    {
                        return preg_replace_callback(
                            '/(?:[^a-zA-Z0-9_\-\.~!\$&\'\(\)\*\+,;=%'.$allowed.']++|%(?![A-Fa-f0-9]{2}))/',
                            function ($matches) {
                                return rawurlencode($matches[0]);
                            },
                            $value
                        );
                    }
                };
            }

            /**
             * @param  \Psr\Http\Message\StreamInterface       $stream
             * @param  int|null                                $size            Stream size when null
             * @param  int                                     $error           One of the UPLOAD_ERR_* constants
             * @param  string|null                             $clientFilename
             * @param  string|null                             $clientMediaType
             * @return \Psr\Http\Message\UploadedFileInterface
             */
            public function createUploadedFile(\Psr\Http\Message\StreamInterface $stream, $size = null, $error = UPLOAD_ERR_OK, $clientFilename = null, $clientMediaType = null)
            {
                if (is_null($size)) {
                    $size = $stream->getSize();
                }

                return new class ($stream, $size, $error, $clientFilename, $clientMediaType) implements \Psr\Http\Message\UploadedFileInterface {
                    private $stream;
                    private $size;
                    private $error;
                    private $clientFilename;
                    private $clientMediaType;
                    private $moved = false;

                    public function __construct(\Psr\Http\Message\StreamInterface $stream, $size, $error, $clientFilename, $clientMediaType)
                    {
                        if (!is_int($error) || $error < 0 || $error > 8) {
                            throw new \InvalidArgumentException('Upload file error status must be one of the "UPLOAD_ERR_*" constants');
                        }

                        if (!is_null($size) && !is_int($size)) {
                            throw new \InvalidArgumentException('Upload file size must be an integer');
                        }

                        if (!is_null($clientFilename) && !is_string($clientFilename)) {
                            throw new \InvalidArgumentException('Upload file client filename must be a string or null');
                        }

                        if (!is_null($clientMediaType) && !is_string($clientMediaType)) {
                            throw new \InvalidArgumentException('Upload file client media type must be a string or null');
                        }

                        $this->stream = $stream;
                        $this->size = $size;
                        $this->error = $error;
                        $this->clientFilename = $clientFilename;
                        $this->clientMediaType = $clientMediaType;
                    }

                    public function getStream()
                    {
                        $this->validateActive();

                        return $this->stream;
                    }

                    public function moveTo($targetPath)
                    {
                        $this->validateActive();

                        if (!is_string($targetPath) || '' === $targetPath) {
                            throw new \InvalidArgumentException('Invalid path provided for move operation, it must be a non-empty string');
                        }

                        $from = $this->stream->getMetadata('uri');

                        if ('cli' !== PHP_SAPI && is_string($from) && is_uploaded_file($from)) {
                            $this->stream->close();
                            $moved = move_uploaded_file($from, $targetPath);
                        } else {
                            $target = @fopen($targetPath, 'wb');
                            $moved = false !== $target;

                            if ($moved) {
                                if ($this->stream->isSeekable()) {
                                    $this->stream->rewind();
                                }

                                while (!$this->stream->eof()) {
                                    fwrite($target, $this->stream->read(1048576));
                                }

                                fclose($target);
                            }
                        }

                        if (!$moved) {
                            throw new \RuntimeException(sprintf('Uploaded file could not be moved to "%s"', $targetPath));
                        }

                        $this->moved = true;
                    }

                    public function getSize()
                    {
                        return $this->size;
                    }

                    public function getError()
                    {
                        return $this->error;
                    }

                    public function getClientFilename()
                    {
                        return $this->clientFilename;
                    }

                    public function getClientMediaType()
                    {
                        return $this->clientMediaType;
                    }

                    private function validateActive()
                    {
                        if (UPLOAD_ERR_OK !== $this->error) {
                            throw new \RuntimeException('Cannot retrieve stream due to upload error');
                        }

                        if ($this->moved) {
                            throw new \RuntimeException('Cannot retrieve stream after it has already been moved');
                        }
                    }
                };
            }
        };
    }
};
