<?php

/**
 * Middlewares wrapping route callbacks.
 *
 * A middleware receives the request and the next handler, it returns a response:
 *
 *     function ($request, callable $next) {
 *         $response = $next();
 *         $response->setHeader('X-Frame-Options', 'DENY');
 *
 *         return $response;
 *     }
 *
 * `$next()` always returns a response: when the callback doesn't return one,
 * its output is used as the body. In that case, unless a middleware returns
 * another response, the next route callbacks are still invoked.
 */
$deps['middleware'] = new class ($deps) {
    /**
     * Dynamic set of dependencies, used to retrieve the request and response services.
     *
     * @var array
     */
    private $deps;

    /**
     * @param array $deps Dynamic set of dependencies.
     */
    public function __construct(array &$deps = [])
    {
        $this->deps = &$deps;
    }

    /**
     * Wrap a route callback with middlewares, the first one is the outermost.
     *
     * @param callable $callback       Route callback
     * @param callable ...$middlewares Middlewares
     *
     * @return callable
     */
    public function wrap(callable $callback, callable ...$middlewares)
    {
        return new class ($this->deps, $callback, $middlewares) {
            private $deps;
            private $callback;
            private $middlewares;

            public function __construct(array &$deps, callable $callback, array $middlewares)
            {
                $this->deps = &$deps;
                $this->callback = $callback;
                $this->middlewares = $middlewares;
            }

            public function __invoke(...$args)
            {
                $request = $this->service('request');

                // response created from the output of a callback not returning one
                $implicit = null;

                $handler = function () use ($args, &$implicit) {
                    ob_start();

                    try {
                        $result = call_user_func_array($this->callback, $args);
                    } catch (Throwable $exception) {
                        ob_end_flush();

                        throw $exception;
                    }

                    $output = ob_get_clean();

                    if (is_object($result) && method_exists($result, 'send')) {
                        if ('' !== $output && method_exists($result, 'body') && method_exists($result, 'setBody')) {
                            $result->setBody($output.$result->body());
                        }

                        return $result;
                    }

                    $status = http_response_code();
                    $implicit = $this->service('response')
                        ->setStatus(is_int($status) ? $status : 200)
                        ->setBody($output);

                    return $implicit;
                };

                foreach (array_reverse($this->middlewares) as $middleware) {
                    $handler = function () use ($middleware, $request, $handler) {
                        return call_user_func($middleware, $request, $handler);
                    };
                }

                $response = $handler();

                if (!is_null($implicit) && (!is_object($response) || $response === $implicit)) {
                    // the callback didn't return a response: the next callbacks aren't skipped
                    $implicit->send();

                    return null;
                }

                return $response;
            }

            /**
             * Wrapped callback, used by the router to read the parameters names.
             */
            public function getCrystalCallable()
            {
                return is_object($this->callback) && method_exists($this->callback, 'getCrystalCallable')
                    ? $this->callback->getCrystalCallable()
                    : $this->callback;
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
    }
};
