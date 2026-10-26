<?php

/*
 * Crystal PHP microframework
 * https://github.com/EmanueleMinotto/crystal
 *
 * Released under the MIT license, see https://github.com/EmanueleMinotto/crystal/blob/master/LICENSE.md
 *
 * This file is built from the src directory of the repository: change those
 * files and run `composer build` instead of editing this file.
 */

### AUTOLOADER ###

### POLYFILLS ###

/**
 * A PHP (7.0+) microframework based on anonymous functions and classes.
 */
return new class () {
    /**
     * Used to store functions and allow recursive callbacks.
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
     * This variable is a constant during an instance.
     *
     * @var null|string
     */
    private $base;

    /**
     * Whether a route callback has returned a response.
     *
     * @var bool
     */
    private $responded = false;

    /**
     * State of the routing: priorities still to be matched, whether a route
     * matched and the methods of routes matching only the path.
     *
     * @var array
     */
    private $routing = [
        'pending' => 0,
        'matched' => false,
        'methods' => [],
        'finalizer' => false,
    ];

    public function __construct()
    {
        // services write on $deps, so it has to be bound to the property
        $deps = &$this->deps;

        ### PHP 7 FEATURES PLACEHOLDER ###
    }

    /**
     * Define a GET route, arguments are the same of the framework instance without the method.
     *
     * @param string   $regex    Regular expression used to match requested URL
     * @param callable $callback Function invoked when there's a match
     * @param float    $priority Set `$callback` priority from 0 (high) to ~1.8e308 (low)
     * @param string   $name     Name of the route
     */
    public function get(string $regex, callable $callback, $priority = 0, $name = null)
    {
        return $this($regex, $callback, 'GET', $priority, $name);
    }

    /**
     * Define a POST route, see `get()`.
     */
    public function post(string $regex, callable $callback, $priority = 0, $name = null)
    {
        return $this($regex, $callback, 'POST', $priority, $name);
    }

    /**
     * Define a PUT route, see `get()`.
     */
    public function put(string $regex, callable $callback, $priority = 0, $name = null)
    {
        return $this($regex, $callback, 'PUT', $priority, $name);
    }

    /**
     * Define a PATCH route, see `get()`.
     */
    public function patch(string $regex, callable $callback, $priority = 0, $name = null)
    {
        return $this($regex, $callback, 'PATCH', $priority, $name);
    }

    /**
     * Define a DELETE route, see `get()`.
     */
    public function delete(string $regex, callable $callback, $priority = 0, $name = null)
    {
        return $this($regex, $callback, 'DELETE', $priority, $name);
    }

    public function __invoke()
    {
        // local references used to keep the function body unchanged
        $deploy = &$this->deploy;
        $matches = &$this->matches;
        $deps = &$this->deps;
        $base = &$this->base;
        $responded = &$this->responded;
        $routing = &$this->routing;

        // base path for each route defined once
        if (is_null($base)) {
            $base = quotemeta(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'));
        }

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
                        if (!empty($_SERVER['REQUEST_URI'])) {
                            return '(?!('.implode('|', $matches).')$).*';
                        }

                        break;
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

        // placeholders like {id} and {id:[0-9]+} are compiled to named subpatterns
        if (func_num_args() >= 2 && is_string($args[0]) && is_object($deps['router'] ?? null) && method_exists($deps['router'], 'compile')) {
            $args[0] = $deps['router']->compile($args[0]);
        }

        // routes can be named using the fifth argument
        if (func_num_args() >= 5 && is_string($args[0]) && !is_null($args[4])) {
            $deps['router']->name($args[4], $args[0]);
        }

        if (!is_null($deploy)) {
            goto invoke_deploy;
        }

        // functions have to be stored only once
        if (empty($_SERVER['REQUEST_URI'])) {
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

            goto invoke_deploy;
        }

        /**
         * Function completing the arguments of a route callback.
         *
         * Parameters without a value from the requested URL receive the service
         * with the same name (like `$request`) or, for classes, an instance made
         * by the container. Route values that PHP can't pass to `int`, `float`,
         * `string` and `bool` parameters (like empty optional subpatterns) are
         * replaced by the default value, or answered with a 404 by the errors service.
         *
         * @param array $args       Arguments from the requested URL
         * @param array $parameters Parameters of the callback, see the router
         *
         * @return array
         */
        $arguments = function (array $args, array $parameters) use (&$deps) {
            $container = $deps['container'] ?? null;
            $container = is_object($container) && method_exists($container, 'has') && method_exists($container, 'get') ? $container : null;

            // services and objects set with a name, settings aren't injected
            $service = function (string $name) use (&$deps, $container) {
                if (array_key_exists($name, $deps)) {
                    $value = $deps[$name];

                    // only factories without arguments are invoked
                    if ($value instanceof Closure) {
                        if ((new ReflectionFunction($value))->getNumberOfRequiredParameters() > 0) {
                            return null;
                        }
                    } elseif (!is_object($value) || is_callable($value)) {
                        return null;
                    }
                } elseif (is_null($container) || !$container->has($name)) {
                    return null;
                }

                if (!is_null($container)) {
                    $value = $container->get($name);
                } elseif ($value instanceof Closure) {
                    $value = $value();
                }

                return is_object($value) ? $value : null;
            };

            foreach ($parameters as $position => $parameter) {
                if ($parameter['variadic']) {
                    break;
                }

                $exists = array_key_exists($position, $args);

                if ($parameter['route'] && $exists) {
                    $value = $args[$position];

                    if (is_null($value) && ($parameter['nullable'] || '' === $parameter['type'])) {
                        continue;
                    }

                    $invalid = is_null($value)
                        || (in_array($parameter['type'], ['int', 'float'], true) && is_string($value) && !is_numeric($value));

                    if (!$invalid) {
                        continue;
                    }

                    if (('' === $value || is_null($value)) && $parameter['optional']) {
                        $args[$position] = $parameter['default'];
                    } elseif ('' === $value && $parameter['nullable']) {
                        $args[$position] = null;
                    } elseif (is_object($deps['errors'] ?? null) && method_exists($deps['errors'], 'isEnabled') && $deps['errors']->isEnabled()) {
                        $deps['errors']->abort(404);
                    }

                    continue;
                }

                if ($exists && !is_null($args[$position])) {
                    continue;
                }

                $value = $service($parameter['name']);

                if (is_null($value) && '' !== $parameter['class'] && !is_null($container) && method_exists($container, 'make')) {
                    if ($container->has($parameter['class']) || class_exists($parameter['class'])) {
                        $value = $container->make($parameter['class']);
                    }
                }

                if (!is_null($value)) {
                    $args[$position] = $value;
                } elseif (!$exists && $parameter['optional']) {
                    // the next parameters can still be injected
                    $args[$position] = $parameter['default'];
                } elseif (!$exists) {
                    break;
                }
            }

            return $args;
        };

        /**
         * Function invoking route callbacks.
         *
         * The route parameters are exposed by the request service, the first
         * response returned by a callback is sent and the next callbacks are skipped.
         * Arrays and JsonSerializable objects returned by a callback are sent as JSON responses.
         *
         * @param callback $cb         Route callback
         * @param array    $args       Arguments of the callback
         * @param array    $params     Named route parameters
         * @param array    $parameters Parameters of the callback
         *
         * @return void
         */
        $dispatch = function ($cb, array $args, array $params, array $parameters = []) use (&$deps, &$responded, $arguments) {
            // the output of all the callbacks is buffered until the end of
            // the script, so a response can set status and headers after it
            static $level = null;

            if (is_null($level) && ob_start()) {
                $level = ob_get_level();
            }

            $errors = $deps['errors'] ?? null;

            if ($responded || (is_object($errors) && method_exists($errors, 'hasSent') && $errors->hasSent())) {
                return;
            }

            if (!empty($deps['request']) && is_object($deps['request']) && method_exists($deps['request'], 'setParams')) {
                $deps['request']->setParams($params);
            }

            ob_start();

            try {
                $result = call_user_func_array($cb, $arguments($args, $parameters));

                if ((is_array($result) || $result instanceof JsonSerializable) && !empty($deps['response'])) {
                    $response = is_callable($deps['response']) ? call_user_func($deps['response']) : $deps['response'];
                    $result = $response->json($result);
                }
            } catch (Throwable $exception) {
                if (!is_object($errors) || !method_exists($errors, 'isEnabled') || !$errors->isEnabled()) {
                    ob_end_flush();

                    throw $exception;
                }

                // the output of the route callbacks is replaced by the error response
                while (ob_get_level() > (int) $level) {
                    ob_end_clean();
                }

                if (!is_null($level) && ob_get_level() === $level) {
                    ob_clean();
                }

                $responded = true;
                $errors->send($errors->handle($exception));

                return;
            }

            $output = ob_get_clean();

            if (!is_object($result) || !method_exists($result, 'send')) {
                echo $output;

                return;
            }

            $responded = true;

            // the output of the callback is the beginning of the response body
            if ('' !== $output && method_exists($result, 'body') && method_exists($result, 'setBody')) {
                $result->setBody($output.$result->body());
                $output = '';
            }

            $result->send();
            echo $output;
        };

        /**
         * Function invoked after the routing, when no route matches the
         * request the errors service (if enabled) sends a 404 or 405 response,
         * when CORS is enabled OPTIONS requests are answered by the router.
         *
         * @return void
         */
        $finalize = function () use (&$finalize, &$routing, &$deps, &$responded) {
            if ($routing['pending'] > 0) {
                // routes with a priority are matched later
                return register_shutdown_function($finalize);
            }

            $errors = $deps['errors'] ?? null;

            if ($routing['matched'] || $responded || (is_object($errors) && method_exists($errors, 'hasSent') && $errors->hasSent())) {
                return;
            }

            $router = $deps['router'] ?? null;
            $cors = $deps['cors'] ?? null;

            // preflight requests, the CORS headers are sent by the cors service
            if (
                'OPTIONS' === strtoupper($_SERVER['REQUEST_METHOD']) &&
                !empty($routing['methods']) &&
                is_object($cors) &&
                method_exists($cors, 'isEnabled') &&
                $cors->isEnabled() &&
                is_object($router) &&
                method_exists($router, 'options') &&
                !headers_sent()
            ) {
                $router->options($routing['methods'])->send();

                return;
            }

            if (
                !is_object($errors) ||
                !method_exists($errors, 'isEnabled') ||
                !$errors->isEnabled()
            ) {
                return;
            }

            $errors->send($errors->notFound($routing['methods']));
        };

        /**
         * Function used as a router.
         *
         * @param string   $regex    Regular expression used to match requested URL
         * @param callback $cb       Function invoked when there's a match
         * @param string   $method   Request method(s)
         * @param float    $priority Set `$cb` priority from 0 (high) to ~1.8e308 (low)
         *
         * @link http://php.net/manual/en/language.types.float.php
         *
         * @return void
         */
        $deploy = function ($regex, $cb, $method = 'GET', $priority = 0) use (&$deploy, &$matches, &$routing, $base, $dispatch, $finalize) {
            // Checking well formed call
            assert(is_string($regex));
            assert(is_callable($cb));
            assert(is_string($method));
            assert(is_numeric($priority));

            // match stored as unique using the Adler-32 algorithm that is faster than md5
            // http://en.wikipedia.org/wiki/Adler-32
            // http://3v4l.org/7MC3j
            $matches[hash('adler32', $regex)] = $regex;

            if (!$routing['finalizer']) {
                register_shutdown_function($finalize);
                $routing['finalizer'] = true;
            }

            if ($priority > 0) {
                ++$routing['pending'];

                // Recursion is used to set callback priority
                return register_shutdown_function(function () use (&$deploy, &$routing, $regex, $cb, $method, $priority) {
                    --$routing['pending'];

                    return $deploy($regex, $cb, $method, $priority - 1);
                });
            }

            if (preg_match('#^'.$base.$regex.'$#', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), $submatches)) {
                if (!preg_match('#'.$method.'#', $_SERVER['REQUEST_METHOD'])) {
                    // used for the 405 response
                    $routing['methods'][] = $method;

                    return;
                }

                $routing['matched'] = true;

                // Named subpatterns are allowed
                // http://it2.php.net/manual/en/regexp.reference.subpatterns.php
                $named = [];
                $values = [];
                $skip = false;

                // If matches is provided, then it is filled with the results of search.
                // $submatches[0] will contain the text that matched the full pattern,
                // $submatches[1] will have the text that matched the first captured parenthesized
                // subpattern, and so on: the value of a named subpattern is listed
                // with its name and then with its number.
                foreach ($submatches as $key => $value) {
                    if (is_string($key)) {
                        $named[$key] = $value;
                        $values[$key] = $value;
                        $skip = true;
                    } elseif ($skip || 0 === $key) {
                        $skip = false;
                    } else {
                        $values[] = $value;
                    }
                }

                $submatches = $values;

                // Wrappers (like protected routes) expose the wrapped callback,
                // so its parameters can be used for named subpatterns.
                $target = is_object($cb) && method_exists($cb, 'getCrystalCallable')
                    ? $cb->getCrystalCallable()
                    : $cb;

                // Snippet used to extract parameter from a callable object.
                if ((is_string($target) && function_exists($target)) || $target instanceof Closure) {
                    $reflector = new ReflectionFunction($target);
                } elseif (is_array($target)) {
                    $reflector = new ReflectionMethod($target[0], $target[1]);
                } elseif (is_object($target)) {
                    $reflector = new ReflectionMethod($target, '__invoke');
                } else {
                    $reflector = new ReflectionMethod($target);
                }
                $params = [];

                foreach ($reflector->getParameters() as $parameter) {
                    // reset to prevent key value
                    $params[$parameter->name] = null;
                }

                // user can use named parameters only if explicitly requested
                $byName = (bool) array_intersect(array_keys($params), array_keys($submatches));

                if ($byName) {
                    $submatches = array_merge($params, $submatches);
                }

                $values = array_values($submatches);

                // parameters of the callback, used to convert the route values
                // and to inject the services when invoking it
                $parameters = [];

                foreach ($reflector->getParameters() as $position => $parameter) {
                    $type = $parameter->getType();
                    $typeName = is_null($type) ? '' : (method_exists($type, 'getName') ? $type->getName() : (string) $type);
                    $builtin = !is_null($type) && method_exists($type, 'isBuiltin') && $type->isBuiltin();
                    $optional = $parameter->isOptional() && $parameter->isDefaultValueAvailable();

                    $parameters[] = [
                        'name' => $parameter->name,
                        // whether the value comes from the requested URL
                        'route' => $byName ? array_key_exists($parameter->name, $named) : $position < count($values),
                        'type' => $builtin ? $typeName : '',
                        'class' => (is_null($type) || $builtin) ? '' : $typeName,
                        'nullable' => is_null($type) || $type->allowsNull(),
                        'optional' => $optional,
                        'default' => $optional ? $parameter->getDefaultValue() : null,
                        'variadic' => $parameter->isVariadic(),
                    ];
                }

                // register_shutdown_function is used to call added functions when script ends
                // http://it2.php.net/manual/en/function.register-shutdown-function.php
                return register_shutdown_function($dispatch, $cb, $values, $named, $parameters);
            }
        };

        // invoking deploy
        invoke_deploy:
        return call_user_func_array($deploy, $args);
    }
};
