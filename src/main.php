<?php

### AUTOLOADER ###

### POLYFILLS ###

/**
 * A PHP (7.0+) microframework based on anonymous functions and classes.
 */
return new class () {
    /**
     * Pattern returned by `router:method-not-allowed`, resolved when the request is dispatched.
     */
    public const METHOD_NOT_ALLOWED = "\0router:method-not-allowed";

    /**
     * Methods checked when an URL is matched with a different request method.
     */
    public const METHODS = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    /**
     * Used to store functions and allow recursive callbacks in CLI mode.
     *
     * @var null|callable
     */
    private $deploy;

    /**
     * Defined matches.
     *
     * @var array
     */
    private $matches = [];

    /**
     * Dependency Injection callbacks, used for settings too.
     *
     * @var array
     */
    private $deps = [];

    /**
     * Whether the script is running in command line mode, detected once.
     *
     * @var null|bool
     */
    private $cli;

    /**
     * Defined routes, dispatched when the script ends.
     *
     * @var array
     */
    private $routes = [];

    /**
     * Patterns returned by `router:not-found`.
     *
     * @var array
     */
    private $notFound = [];

    /**
     * Middlewares wrapping every request.
     *
     * @var callable[]
     */
    private $middlewares = [];

    /**
     * Stack of the groups currently defined.
     *
     * @var array
     */
    private $groups = [];

    /**
     * When enabled routes are dispatched only using `handle()`.
     *
     * @var bool
     */
    private $testing = false;

    /**
     * Whether the shutdown function dispatching routes is registered.
     *
     * @var bool
     */
    private $registered = false;

    public function __construct()
    {
        // services write on $deps, so it has to be bound to the property
        $deps = &$this->deps;

        ### PHP 7 FEATURES PLACEHOLDER ###
    }

    public function __invoke()
    {
        // local references used to keep the function body unchanged
        $deploy = &$this->deploy;
        $deps = &$this->deps;

        // used to shorten code
        $args = func_get_args();

        // used to retrieve currently defined matches
        // http://www.php.net/manual/en/regexp.reference.conditional.php
        // http://stackoverflow.com/questions/14598972/catch-all-regular-expression
        switch (func_num_args()) {
            case 1:
                // Set of utilities
                switch ($args[0]) {
                    case 'router:not-found':
                        if (!$this->isCli() || $this->testing) {
                            $pattern = '(?!('.implode('|', $this->matches).')$).*';
                            $this->notFound[$pattern] = true;

                            return $pattern;
                        }

                        break;

                    case 'router:method-not-allowed':
                        return static::METHOD_NOT_ALLOWED;

                    case 'router:allowed-methods':
                        $request = $this->service('request');

                        return $this->allowedMethods($request->path(), $this->basePath($request));
                }

                // using $GLOBALS as a container, variable names must match
                // this regular expression
                // http://www.php.net/manual/en/language.variables.basics.php
                if (is_scalar($args[0]) && preg_match('#^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*#', $args[0])) {
                    if (!empty($deps['container']) && is_object($deps['container'])) {
                        // if there's a container, use it
                        return $deps['container']->get($args[0]);
                    }

                    return is_callable($deps[$args[0]])
                        ? call_user_func($deps[$args[0]])
                        : $deps[$args[0]];
                }

                break;

            case 2:
                // using $GLOBALS as a container, variable names must match
                // this regular expression
                // http://www.php.net/manual/en/language.variables.basics.php
                if (is_scalar($args[0]) && preg_match('#^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*#', $args[0])) {
                    // functions used for Dependency Injection and settings
                    if (!empty($deps['container']) && is_object($deps['container'])) {
                        // if there's a container, use it
                        $deps['container']->set($args[0], $args[1]);

                        return;
                    }

                    return $deps[$args[0]] = $args[1];
                }

                break;
        }

        // a pattern followed by a callback is a route, even in command line mode,
        // so routes can be listed and tested without a webserver
        $isRoute = isset($args[0], $args[1]) && is_string($args[0]) && is_callable($args[1]);

        if ($isRoute && (!$this->isCli() || !is_callable($args[0]))) {
            return $this->route(
                $args[2] ?? 'GET',
                $args[0],
                $args[1],
                ['priority' => $args[3] ?? 0]
            );
        }

        if (is_null($deploy)) {
            /**
             * Command line interface for the main function.
             *
             * @param callback $cb       Function invoked when script ends
             * @param int      $priority Set `$cb` priority from 0 (high) to ~1.8e308 (low)
             *
             * @link http://php.net/manual/en/language.types.float.php
             *
             * @return void
             */
            $deploy = function ($cb, $priority = 0) use (&$deploy) {
                // Checking well formed call
                assert(is_callable($cb));
                assert(is_numeric($priority));

                if ($priority > 0) {
                    // Recursion is used to set callback priority
                    return register_shutdown_function($deploy, $cb, $priority - 1);
                }

                /**
                 * Arguments passed to the script.
                 *
                 * @link http://php.net/manual/en/reserved.variables.argv.php
                 *
                 * @var array
                 */
                $argv = $GLOBALS['argv'];

                $argv[0] = $cb;

                // register_shutdown_function is used to call added functions when script ends
                // http://it2.php.net/manual/en/function.register-shutdown-function.php
                return call_user_func_array('register_shutdown_function', array_values($argv));
            };
        }

        return call_user_func_array($deploy, $args);
    }

    /**
     * Define a route.
     *
     * Options:
     *   priority   — from 0 (high) to ~1.8e308 (low), default 0
     *   middleware — list of callables `function ($request, callable $next)`
     *   name       — route name, used as OpenAPI operationId
     *   openapi    — OpenAPI operation object merged in `openapi()`
     *
     * @param string|string[] $methods Request methods, a regular expression or a list
     * @param string          $pattern Regular expression or `{name}`/`{name:regex}` placeholders
     * @param callable        $cb      Function invoked when there's a match
     * @param array           $options Route options
     *
     * @return $this
     */
    public function route($methods, string $pattern, callable $cb, array $options = [])
    {
        $middleware = isset($options['middleware']) ? (array) $options['middleware'] : [];
        $openapi = $options['openapi'] ?? [];
        $prefix = '';

        foreach ($this->groups as $group) {
            $prefix .= $group['prefix'];
            $middleware = array_merge($group['middleware'], $middleware);
            $openapi = array_merge_recursive($group['openapi'], $openapi);
        }

        $special = static::METHOD_NOT_ALLOWED === $pattern || isset($this->notFound[$pattern]);
        $path = $special ? $pattern : $prefix.$pattern;
        $regex = $special ? $pattern : $this->compile($path);

        if (is_array($methods)) {
            $list = array_values(array_unique(array_map('strtoupper', $methods)));
            $methods = '^('.implode('|', array_map('preg_quote', $list)).')$';
        }

        if (!$special) {
            // match stored as unique using the Adler-32 algorithm that is faster than md5
            // http://en.wikipedia.org/wiki/Adler-32
            // http://3v4l.org/7MC3j
            $this->matches[hash('adler32', $regex)] = $regex;
        }

        $this->routes[] = [
            'methods' => $methods,
            'list' => $list ?? null,
            'path' => $path,
            'regex' => $regex,
            'callback' => $cb,
            'priority' => $options['priority'] ?? 0,
            'middleware' => $middleware,
            'name' => $options['name'] ?? null,
            'openapi' => $openapi,
            'special' => $special,
        ];

        $this->register();

        return $this;
    }

    public function get(string $pattern, callable $cb, array $options = [])
    {
        return $this->route(['GET', 'HEAD'], $pattern, $cb, $options);
    }

    public function post(string $pattern, callable $cb, array $options = [])
    {
        return $this->route(['POST'], $pattern, $cb, $options);
    }

    public function put(string $pattern, callable $cb, array $options = [])
    {
        return $this->route(['PUT'], $pattern, $cb, $options);
    }

    public function patch(string $pattern, callable $cb, array $options = [])
    {
        return $this->route(['PATCH'], $pattern, $cb, $options);
    }

    public function delete(string $pattern, callable $cb, array $options = [])
    {
        return $this->route(['DELETE'], $pattern, $cb, $options);
    }

    public function options(string $pattern, callable $cb, array $options = [])
    {
        return $this->route(['OPTIONS'], $pattern, $cb, $options);
    }

    public function any(string $pattern, callable $cb, array $options = [])
    {
        return $this->route('.*', $pattern, $cb, $options);
    }

    /**
     * Define routes sharing a prefix, middlewares and OpenAPI options.
     *
     * @param string   $prefix  Prefix prepended to every route pattern
     * @param callable $routes  Function receiving the framework instance
     * @param array    $options `middleware` and `openapi` options
     *
     * @return $this
     */
    public function group(string $prefix, callable $routes, array $options = [])
    {
        $this->groups[] = [
            'prefix' => $prefix,
            'middleware' => isset($options['middleware']) ? (array) $options['middleware'] : [],
            'openapi' => $options['openapi'] ?? [],
        ];

        try {
            call_user_func($routes, $this);
        } finally {
            array_pop($this->groups);
        }

        return $this;
    }

    /**
     * Add a middleware wrapping every request, including the not matched ones.
     *
     * @param callable $middleware Function `function ($request, callable $next)`
     *
     * @return $this
     */
    public function middleware(callable $middleware)
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    /**
     * Toggle the testing mode: routes are dispatched only calling `handle()`.
     *
     * @return $this
     */
    public function testing(bool $enabled = true)
    {
        $this->testing = $enabled;

        return $this;
    }

    /**
     * Dispatch a request in-process and return the response without sending it.
     *
     * Options: headers, query, body, json, form, server.
     *
     * @return object Response
     */
    public function handle(string $method, string $uri, array $options = [])
    {
        $request = $this->service('request');
        $server = ($options['server'] ?? []) + [
            'REQUEST_METHOD' => strtoupper($method),
            'REQUEST_URI' => $uri,
            'SCRIPT_NAME' => '/index.php',
        ];
        $headers = $options['headers'] ?? [];
        $body = $options['body'] ?? '';

        if (isset($options['json'])) {
            $body = json_encode($options['json']);
            $headers += ['Content-Type' => 'application/json'];
        }

        if (isset($options['form'])) {
            $body = http_build_query($options['form']);
            $headers += ['Content-Type' => 'application/x-www-form-urlencoded'];
        }

        $request->initialize([
            'server' => $server,
            'headers' => $headers,
            'query' => $options['query'] ?? null,
            'body' => $body,
        ]);

        $response = $this->process($request);

        // the next request will be built from globals again
        $request->initialize();

        return $response;
    }

    /**
     * List defined routes.
     *
     * @return array
     */
    public function routes()
    {
        $routes = [];

        foreach ($this->routes as $route) {
            if ($route['special']) {
                continue;
            }

            $routes[] = [
                'methods' => $this->routeMethods($route),
                'path' => $route['path'],
                'name' => $route['name'],
                'priority' => $route['priority'],
            ];
        }

        return $routes;
    }

    /**
     * Generate an OpenAPI 3 document from the defined routes.
     *
     * Routes that can't be represented as an OpenAPI path are skipped.
     *
     * @param array $info  OpenAPI info object, `title` and `version` are required
     * @param array $extra Root keys merged into the document (servers, components...)
     *
     * @return array
     */
    public function openapi(array $info = [], array $extra = [])
    {
        $paths = [];

        foreach ($this->routes as $route) {
            if ($route['special'] || false === ($path = $this->openapiPath($route['path'], $parameters))) {
                continue;
            }

            $methods = array_diff($this->routeMethods($route), is_null($route['list']) ? ['HEAD', 'OPTIONS'] : ['HEAD']);

            foreach ($methods as $method) {
                $operation = $route['openapi'];

                if (!empty($route['name']) && !isset($operation['operationId'])) {
                    $operation['operationId'] = $route['name'];
                }

                if (!empty($parameters)) {
                    $operation['parameters'] = array_merge(
                        $parameters,
                        $operation['parameters'] ?? []
                    );
                }

                if (empty($operation['responses'])) {
                    $operation['responses'] = ['200' => ['description' => 'OK']];
                }

                $paths[$path][strtolower($method)] = $operation;
            }
        }

        return array_merge([
            'openapi' => '3.0.3',
            'info' => $info + ['title' => 'API', 'version' => '1.0.0'],
            'paths' => empty($paths) ? new stdClass() : $paths,
        ], $extra);
    }

    /**
     * Resolve a dependency.
     */
    private function service(string $id)
    {
        return $this->__invoke($id);
    }

    private function isCli()
    {
        if (is_null($this->cli)) {
            $this->cli = empty($_SERVER['REQUEST_URI']);
        }

        return $this->cli;
    }

    /**
     * Register once the function dispatching routes when the script ends.
     */
    private function register()
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;

        register_shutdown_function(function () {
            if ($this->testing) {
                return;
            }

            if ($this->isCli()) {
                $this->console();

                return;
            }

            $request = $this->service('request');
            $response = $this->process($request);

            if (!headers_sent()) {
                http_response_code($response->getStatusCode());

                foreach ($response->getHeaders() as $name => $values) {
                    foreach ((array) $values as $i => $value) {
                        header($name.': '.$value, 0 === $i);
                    }
                }
            }

            echo (string) $response->getBody();
        });
    }

    /**
     * Built-in commands available in command line mode.
     */
    private function console()
    {
        $command = $GLOBALS['argv'][1] ?? null;

        if ('crystal:routes' === $command) {
            foreach ($this->routes() as $route) {
                echo rtrim(sprintf('%-24s %-40s %s', implode('|', $route['methods']), $route['path'], $route['name'])), "\n";
            }
        }

        if ('crystal:openapi' === $command) {
            echo json_encode($this->openapi(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
        }
    }

    /**
     * Compile `{name}` and `{name:regex}` placeholders to named subpatterns.
     */
    private function compile(string $pattern)
    {
        return preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::((?:[^{}]++|\{[^{}]*\})+))?\}#',
            function ($match) {
                return sprintf('(?P<%s>%s)', $match[1], $match[2] ?? '[^/]+');
            },
            $pattern
        );
    }

    private function basePath($request)
    {
        // base path for each route
        return quotemeta(rtrim(dirname($request->server('SCRIPT_NAME', '/')), '/\\'));
    }

    /**
     * Dispatch the current request through middlewares and routes.
     *
     * @return object Response
     */
    private function process($request)
    {
        $core = function ($request) {
            $method = $request->method();
            $path = $request->path();
            $base = $this->basePath($request);
            $responses = [];
            $matched = false;
            $done = 0;

            // routes defined while dispatching are dispatched too
            while ($done < count($this->routes)) {
                $queue = [];

                foreach (array_slice($this->routes, $done, null, true) as $index => $route) {
                    // differenced FIFO queues for each priority
                    $queue[] = [$route['priority'] > 0 ? ceil($route['priority']) : 0, $index, $route];
                }

                $done = count($this->routes);

                usort($queue, function ($a, $b) {
                    return $a[0] == $b[0] ? $a[1] - $b[1] : ($a[0] < $b[0] ? -1 : 1);
                });

                foreach ($queue as $item) {
                    $route = $item[2];

                    if (static::METHOD_NOT_ALLOWED === $route['regex']) {
                        if ($this->hasMatch($method, $path, $base) || empty($allowed = $this->allowedMethods($path, $base))) {
                            continue;
                        }

                        $matched = true;
                        $response = $this->invoke($route, [], $request);

                        if (200 === $response->getStatusCode()) {
                            $response = $response->withStatus(405);
                        }

                        $responses[] = $response->withHeader('Allow', implode(', ', $allowed));

                        continue;
                    }

                    if (false === ($args = $this->match($route, $method, $path, $base))) {
                        continue;
                    }

                    $matched = true;
                    $responses[] = $this->invoke($route, $args, $request);
                }
            }

            if (!$matched) {
                return $this->fallback($request, $method, $path, $base);
            }

            return $this->merge($responses);
        };

        try {
            $response = $this->pipeline($this->middlewares, $core, $request);
        } catch (Throwable $exception) {
            $response = $this->service('errors')->render($exception, $request);
        }

        if ('HEAD' === $request->method()) {
            $response = $response->withBody('');
        }

        return $response;
    }

    /**
     * Arguments passed to the route callback, false if the route doesn't match.
     *
     * @return array|false
     */
    private function match(array $route, string $method, string $path, string $base)
    {
        if (
            !preg_match('#'.$route['methods'].'#', $method) ||
            !preg_match('#^'.$base.$route['regex'].'$#', $path, $submatches)
        ) {
            return false;
        }

        // Named subpatterns are allowed
        // http://it2.php.net/manual/en/regexp.reference.subpatterns.php
        $named = array_filter($submatches, 'is_string', ARRAY_FILTER_USE_KEY);
        $submatches = array_unique($submatches);
        // If matches is provided, then it is filled with the results of search.
        // $submatches[0] will contain the text that matched the full pattern,
        // $submatches[1] will have the text that matched the first captured parenthesized
        // subpattern, and so on.
        unset($submatches[0]);

        // Snippet used to extract parameter from a callable object.
        $reflector = $this->reflect($route['callback']);
        $params = [];

        foreach ($reflector->getParameters() as $parameter) {
            // reset to prevent key value
            $params[$parameter->name] = null;
        }

        // user can use named parameters only if explicitly requested
        if (array_intersect(array_keys($params), array_keys($submatches))) {
            $submatches = array_merge($params, $submatches);
        }

        return ['named' => $named, 'arguments' => array_values($submatches)];
    }

    private function reflect(callable $cb)
    {
        if ($cb instanceof Closure || (is_string($cb) && false === strpos($cb, '::'))) {
            return new ReflectionFunction($cb);
        }

        if (is_array($cb)) {
            return new ReflectionMethod($cb[0], $cb[1]);
        }

        if (is_object($cb)) {
            return new ReflectionMethod($cb, '__invoke');
        }

        return new ReflectionMethod($cb);
    }

    /**
     * Whether a route matches both the requested URL and method.
     */
    private function hasMatch(string $method, string $path, string $base)
    {
        foreach ($this->routes as $route) {
            if (!$route['special'] && false !== $this->match($route, $method, $path, $base)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Methods allowed for the requested URL.
     *
     * @return string[]
     */
    private function allowedMethods(string $path, string $base)
    {
        $allowed = [];

        foreach ($this->routes as $route) {
            if (!$route['special'] && preg_match('#^'.$base.$route['regex'].'$#', $path)) {
                $allowed = array_merge($allowed, $this->routeMethods($route));
            }
        }

        return array_values(array_intersect(static::METHODS, $allowed));
    }

    /**
     * Methods matched by a route.
     *
     * @return string[]
     */
    private function routeMethods(array $route)
    {
        if (!is_null($route['list'])) {
            return $route['list'];
        }

        return array_values(array_filter(static::METHODS, function ($method) use ($route) {
            return preg_match('#'.$route['methods'].'#', $method);
        }));
    }

    /**
     * Response used when no routes match: 404, 405 or the automatic OPTIONS one.
     */
    private function fallback($request, string $method, string $path, string $base)
    {
        $allowed = $this->allowedMethods($path, $base);

        if (empty($allowed)) {
            return $this->service('errors')->response(404);
        }

        if (!in_array('OPTIONS', $allowed)) {
            $allowed[] = 'OPTIONS';
        }

        if ('OPTIONS' === $method) {
            return $this->service('response')->noContent()->withHeader('Allow', implode(', ', $allowed));
        }

        return $this->service('errors')->response(405, null, [], ['Allow' => implode(', ', $allowed)]);
    }

    /**
     * Invoke a route callback through its middlewares.
     */
    private function invoke(array $route, array $args, $request)
    {
        $request->setParams($args['named'] ?? []);

        $handler = function () use ($route, $args) {
            $level = ob_get_level();
            $code = http_response_code();

            ob_start();

            try {
                $result = call_user_func_array($route['callback'], $args['arguments'] ?? []);
            } finally {
                $output = '';

                // buffers left open by the callback are collected too
                while (ob_get_level() > $level) {
                    $output = ob_get_clean().$output;
                }
            }

            // status code set using http_response_code() is preserved
            $status = http_response_code();
            $status = is_int($status) && (!$this->testing || $status !== $code) ? $status : 200;

            return $this->toResponse($result, $output, $status);
        };

        try {
            return $this->pipeline($route['middleware'], $handler, $request);
        } catch (Throwable $exception) {
            return $this->service('errors')->render($exception, $request);
        }
    }

    /**
     * Wrap a callable with middlewares, the first one is the outer one.
     */
    private function pipeline(array $middlewares, callable $core, $request)
    {
        $next = function ($request) use ($core) {
            return $this->toResponse(call_user_func($core, $request));
        };

        foreach (array_reverse($middlewares) as $middleware) {
            $next = function ($request) use ($middleware, $next) {
                return $this->toResponse(call_user_func($middleware, $request, $next));
            };
        }

        return $next($request);
    }

    /**
     * Convert a callback result to a response.
     *
     * Responses are kept, arrays and JsonSerializable objects are encoded as JSON,
     * strings are appended to the output.
     */
    private function toResponse($result, string $output = '', int $status = 200)
    {
        $factory = $this->service('response');

        if ($factory->isResponse($result)) {
            return '' === $output ? $result : $result->withBody($output.$result->getBody());
        }

        if (is_array($result) || $result instanceof JsonSerializable || $result instanceof stdClass) {
            return '' === $output ? $factory->json($result, $status) : $factory->create($output.$factory->json($result)->getBody(), $status);
        }

        if (is_string($result)) {
            $output .= $result;
        }

        return $factory->create($output, $status);
    }

    /**
     * Merge the responses of multiple matched routes.
     */
    private function merge(array $responses)
    {
        $merged = array_shift($responses);

        foreach ($responses as $response) {
            $merged = $merged->withBody($merged->getBody().$response->getBody());

            if (200 !== $response->getStatusCode()) {
                $merged = $merged->withStatus($response->getStatusCode());
            }

            foreach ($response->getHeaders() as $name => $values) {
                $merged = $merged->withHeader($name, $values);
            }
        }

        return $merged;
    }

    /**
     * Convert a route path to an OpenAPI path, false if it isn't possible.
     *
     * @return string|false
     */
    private function openapiPath(string $path, &$parameters)
    {
        $parameters = [];

        $path = preg_replace_callback(
            '#\(\?P?<([a-zA-Z_][a-zA-Z0-9_]*)>((?:[^()]++|\((?2)\))*)\)|\{([a-zA-Z_][a-zA-Z0-9_]*)(?::((?:[^{}]++|\{[^{}]*\})+))?\}#',
            function ($match) use (&$parameters) {
                $name = empty($match[1]) ? $match[3] : $match[1];
                $regex = empty($match[1]) ? ($match[4] ?? '') : $match[2];

                $parameters[] = [
                    'name' => $name,
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => preg_match('#^(\\\\d|\[0-9\])[+*]$#', $regex) ? 'integer' : 'string'],
                ];

                return '{'.$name.'}';
            },
            $path
        );

        // escaped characters are literals
        $path = preg_replace('#\\\\([/.\-])#', '$1', $path);

        if (preg_match('#[()\[\]*+?|^$\\\\]#', $path)) {
            return false;
        }

        return '' === $path ? '/' : $path;
    }
};
