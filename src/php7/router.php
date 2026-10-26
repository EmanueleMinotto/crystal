<?php

/**
 * Named routes, URL generation and groups of routes.
 *
 * URLs can be generated only for routes made of literal characters,
 * escaped characters and named subpatterns, like `/posts/(?P<id>[0-9]+)`.
 */
$deps['router'] = new class ($deps, $this) {
    /**
     * Dynamic set of dependencies, used to retrieve the middleware service.
     *
     * @var array
     */
    private $deps;

    /**
     * The framework instance, used to define routes.
     *
     * @var callable
     */
    private $mf;

    /**
     * Named routes, name => regular expression.
     *
     * @var string[]
     */
    private $routes = [];

    /**
     * Whether only the first route matching a request is invoked.
     *
     * @var bool
     */
    private $firstMatch = false;

    /**
     * @param array    $deps Dynamic set of dependencies.
     * @param callable $mf   The framework instance.
     */
    public function __construct(array &$deps, callable $mf)
    {
        $this->deps = &$deps;
        $this->mf = $mf;
    }

    /**
     * Invoke only the first route matching the request (by priority, then
     * in the order they're defined), instead of every matching route.
     */
    public function firstMatch(bool $enabled = true)
    {
        $this->firstMatch = $enabled;
    }

    public function isFirstMatch(): bool
    {
        return $this->firstMatch;
    }

    /**
     * Define a named route, arguments are the same of the framework instance.
     */
    public function route(string $name, string $regex, callable $callback, string $method = 'GET', $priority = 0)
    {
        $this->name($name, $regex);

        return call_user_func($this->mf, $regex, $callback, $method, $priority);
    }

    /**
     * Give a name to a route regular expression.
     *
     * @throws InvalidArgumentException If the name is already used
     */
    public function name(string $name, string $regex)
    {
        if (isset($this->routes[$name])) {
            throw new InvalidArgumentException(sprintf('Route "%s" already defined.', $name));
        }

        $this->routes[$name] = $this->compile($regex);
    }

    public function has(string $name): bool
    {
        return isset($this->routes[$name]);
    }

    /**
     * Compile the placeholders of a route to named subpatterns: `{id}` matches
     * a segment of the path (`[^/]+`), `{id:[0-9]+}` uses its own pattern.
     *
     * Quantifiers (like `{2}` or `{1,3}`) and escaped braces aren't placeholders.
     */
    public function compile(string $regex): string
    {
        if (false === strpos($regex, '{')) {
            return $regex;
        }

        $compiled = '';
        $length = strlen($regex);

        for ($i = 0; $i < $length; ++$i) {
            $char = $regex[$i];

            if ('\\' === $char) {
                $compiled .= substr($regex, $i, 2);
                ++$i;

                continue;
            }

            if ('{' !== $char || !preg_match('#\G\{([a-zA-Z_][a-zA-Z0-9_]*)([:}])#', $regex, $matches, 0, $i)) {
                $compiled .= $char;

                continue;
            }

            if ('}' === $matches[2]) {
                $compiled .= '(?P<'.$matches[1].'>[^/]+)';
                $i += strlen($matches[0]) - 1;

                continue;
            }

            // the pattern ends at the brace closing the placeholder
            $start = $i + strlen($matches[0]);
            $depth = 1;

            for ($end = $start; $end < $length; ++$end) {
                if ('\\' === $regex[$end]) {
                    ++$end;
                } elseif ('{' === $regex[$end]) {
                    ++$depth;
                } elseif ('}' === $regex[$end] && 0 === --$depth) {
                    break;
                }
            }

            // not closed, it's left unchanged
            if ($depth > 0) {
                $compiled .= $char;

                continue;
            }

            $compiled .= '(?P<'.$matches[1].'>'.substr($regex, $start, $end - $start).')';
            $i = $end;
        }

        return $compiled;
    }

    /**
     * Generate the URL of a named route.
     *
     * Values are URL encoded and must match their subpattern,
     * parameters not used by the route are added to the query string.
     *
     * @throws InvalidArgumentException If the route isn't defined, a parameter is missing or invalid
     * @throws LogicException           If the route can't be reversed
     */
    public function url(string $name, array $params = []): string
    {
        if (!isset($this->routes[$name])) {
            throw new InvalidArgumentException(sprintf('Route "%s" not defined.', $name));
        }

        $path = '';

        foreach ($this->parse($name, $this->routes[$name]) as $part) {
            if (is_string($part)) {
                $path .= $part;

                continue;
            }

            list($param, $pattern) = $part;

            if (!array_key_exists($param, $params)) {
                throw new InvalidArgumentException(sprintf('Missing parameter "%s" for route "%s".', $param, $name));
            }

            // the router matches the URL encoded path
            $value = rawurlencode((string) $params[$param]);
            unset($params[$param]);

            if (!preg_match('#^(?:'.$pattern.')$#', $value)) {
                throw new InvalidArgumentException(sprintf(
                    'Parameter "%s" of route "%s" must match "%s", "%s" given.',
                    $param,
                    $name,
                    $pattern,
                    $value
                ));
            }

            $path .= $value;
        }

        $base = empty($_SERVER['REQUEST_URI']) ? '' : rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
        $query = empty($params) ? '' : '?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        return $base.$path.$query;
    }

    /**
     * Define a group of routes, sharing a prefix of the path, a prefix
     * of the names and middlewares (the first one is the outermost).
     *
     * The callback receives an object with the same methods of the framework
     * instance for routes (with the name as fifth argument), `get`, `post`,
     * `put`, `patch`, `delete`, `route` and `group`.
     *
     * @param string     $prefix      Prefix of the regular expressions
     * @param callable   $define      Callback defining the routes
     * @param callable[] $middlewares Middlewares of the routes
     * @param string     $name        Prefix of the names
     */
    public function group(string $prefix, callable $define, array $middlewares = [], string $name = '')
    {
        $define(new class ($this, $this->deps, $prefix, $middlewares, $name) {
            private $router;
            private $deps;
            private $prefix;
            private $middlewares;
            private $name;

            public function __construct($router, array &$deps, string $prefix, array $middlewares, string $name)
            {
                $this->router = $router;
                $this->deps = &$deps;
                $this->prefix = $prefix;
                $this->middlewares = $middlewares;
                $this->name = $name;
            }

            public function __invoke(string $regex, callable $callback, string $method = 'GET', $priority = 0, $name = null)
            {
                assert(is_null($name) || is_string($name));

                if (!empty($this->middlewares)) {
                    $middleware = $this->deps['middleware'];
                    $middleware = is_callable($middleware) ? call_user_func($middleware) : $middleware;
                    $callback = $middleware->wrap($callback, ...$this->middlewares);
                }

                if (is_null($name)) {
                    return call_user_func($this->router->instance(), $this->prefix.$regex, $callback, $method, $priority);
                }

                return $this->router->route($this->name.$name, $this->prefix.$regex, $callback, $method, $priority);
            }

            public function route(string $name, string $regex, callable $callback, string $method = 'GET', $priority = 0)
            {
                return $this($regex, $callback, $method, $priority, $name);
            }

            public function get(string $regex, callable $callback, $priority = 0, $name = null)
            {
                return $this($regex, $callback, 'GET', $priority, $name);
            }

            public function post(string $regex, callable $callback, $priority = 0, $name = null)
            {
                return $this($regex, $callback, 'POST', $priority, $name);
            }

            public function put(string $regex, callable $callback, $priority = 0, $name = null)
            {
                return $this($regex, $callback, 'PUT', $priority, $name);
            }

            public function patch(string $regex, callable $callback, $priority = 0, $name = null)
            {
                return $this($regex, $callback, 'PATCH', $priority, $name);
            }

            public function delete(string $regex, callable $callback, $priority = 0, $name = null)
            {
                return $this($regex, $callback, 'DELETE', $priority, $name);
            }

            /**
             * Nested group, prefixes are concatenated and the outer middlewares are the outermost.
             */
            public function group(string $prefix, callable $define, array $middlewares = [], string $name = '')
            {
                $this->router->group(
                    $this->prefix.$prefix,
                    $define,
                    array_merge($this->middlewares, $middlewares),
                    $this->name.$name
                );
            }
        });
    }

    /**
     * The framework instance, used by groups to define routes.
     *
     * @return callable
     */
    public function instance()
    {
        return $this->mf;
    }

    /**
     * Methods listed by route methods, only simple lists like `GET|POST` can be listed.
     *
     * @param string[] $methods Methods (regular expressions) of the routes
     *
     * @return string[]
     */
    public function allowedMethods(array $methods): array
    {
        $allowed = [];

        foreach ($methods as $method) {
            if (preg_match('#^[A-Za-z]+(\|[A-Za-z]+)*$#', $method)) {
                $allowed = array_merge($allowed, explode('|', strtoupper($method)));
            }
        }

        return array_values(array_unique($allowed));
    }

    /**
     * Response of an OPTIONS request matching only the path of routes, sent when CORS is enabled.
     *
     * @param string[] $methods Methods (regular expressions) of the routes matching the path
     *
     * @return object
     */
    public function options(array $methods)
    {
        $service = $this->deps['response'];
        $response = is_callable($service) ? call_user_func($service) : $service;
        $allowed = $this->allowedMethods($methods);

        $response->setStatus(204);

        if (!empty($allowed)) {
            $response->setHeader('Allow', implode(', ', array_unique(array_merge($allowed, ['OPTIONS']))));
        }

        return $response;
    }

    /**
     * Split a route in literal parts and [name, subpattern] parameters.
     *
     * @throws LogicException If the route contains other regular expression constructs
     */
    private function parse(string $name, string $regex): array
    {
        $parts = [];
        $literal = '';
        $length = strlen($regex);
        $fail = function ($reason) use ($name, $regex) {
            return new LogicException(sprintf('The URL of route "%s" (%s) can\'t be generated: %s.', $name, $regex, $reason));
        };

        for ($i = 0; $i < $length; ++$i) {
            $char = $regex[$i];

            if ('\\' === $char) {
                $next = $regex[$i + 1] ?? '';

                // \d, \w, \s, ... are character classes
                if ('' === $next || ctype_alnum($next)) {
                    throw $fail('character classes are allowed only in named subpatterns');
                }

                $literal .= $next;
                ++$i;

                continue;
            }

            if ('(' === $char) {
                if (!preg_match('#\G\(\?P?(?:<([a-zA-Z_][a-zA-Z0-9_]*)>|\'([a-zA-Z_][a-zA-Z0-9_]*)\')#', $regex, $matches, 0, $i)) {
                    throw $fail('only named subpatterns are allowed');
                }

                $end = $this->closingParenthesis($regex, $i);

                if (is_null($end)) {
                    throw $fail('unbalanced parentheses');
                }

                if (isset($regex[$end + 1]) && false !== strpos('?*+{', $regex[$end + 1])) {
                    throw $fail('quantifiers aren\'t allowed after named subpatterns');
                }

                if ('' !== $literal) {
                    $parts[] = $literal;
                    $literal = '';
                }

                $start = $i + strlen($matches[0]);
                $parts[] = ['' !== $matches[1] ? $matches[1] : $matches[2], substr($regex, $start, $end - $start)];
                $i = $end;

                continue;
            }

            if (false !== strpos('.[]()|*+?{}^$', $char)) {
                throw $fail(sprintf('"%s" isn\'t allowed outside named subpatterns', $char));
            }

            $literal .= $char;
        }

        if ('' !== $literal) {
            $parts[] = $literal;
        }

        return $parts;
    }

    /**
     * Position of the parenthesis closing the one at `$start`,
     * escaped characters and character classes are skipped.
     *
     * @return int|null
     */
    private function closingParenthesis(string $regex, int $start)
    {
        $depth = 0;
        $class = false;
        $length = strlen($regex);

        for ($i = $start; $i < $length; ++$i) {
            $char = $regex[$i];

            if ('\\' === $char) {
                ++$i;
            } elseif ($class) {
                $class = ']' !== $char;
            } elseif ('[' === $char) {
                $class = true;

                // a "]" right after "[" or "[^" is a literal character
                if (']' === ($regex[$i + 1] ?? '')) {
                    ++$i;
                } elseif ('^' === ($regex[$i + 1] ?? '') && ']' === ($regex[$i + 2] ?? '')) {
                    $i += 2;
                }
            } elseif ('(' === $char) {
                ++$depth;
            } elseif (')' === $char && 0 === --$depth) {
                return $i;
            }
        }

        return null;
    }
};
