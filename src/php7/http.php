<?php

/**
 * Exposes an object via $deps['http'] to integrate PSR-7 messages
 * and PSR-15 handlers and middlewares.
 *
 * Everything is opt-in: interfaces and implementations aren't bundled,
 * they're used only when installed (e.g. nyholm/psr7 and psr/http-server-middleware).
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
     * Get the PSR-17 factory, a known implementation is used when not defined.
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
            throw new \RuntimeException('No PSR-17 factory available: install one (e.g. nyholm/psr7) or call setFactory()');
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

        return is_a('Psr\Http\Message\ServerRequestInterface', ltrim($type, '?'), true);
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
};
