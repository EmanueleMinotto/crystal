<?php

/**
 * Exposes a Cross-Origin Resource Sharing middleware factory via $deps['cors'].
 *
 * @see https://fetch.spec.whatwg.org/#http-cors-protocol
 */
$deps['cors'] = new class ($this) {
    /**
     * Framework instance, used to resolve other services.
     *
     * @var callable
     */
    private $mf;

    public function __construct(callable $mf)
    {
        $this->mf = $mf;
    }

    /**
     * Create the CORS middleware.
     *
     * Options:
     *   origins     — allowed origins, '*' or a list (default '*'), `*.example.com` wildcards allowed
     *   methods     — allowed methods (default GET, HEAD, POST, PUT, PATCH, DELETE)
     *   headers     — allowed request headers (default: the requested ones)
     *   expose      — response headers exposed to the client
     *   credentials — allow cookies and authorization headers (default false)
     *   max_age     — seconds preflight responses can be cached (default 600)
     *
     * @return callable
     */
    public function middleware(array $options = [])
    {
        $options += [
            'origins' => '*',
            'methods' => ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'],
            'headers' => null,
            'expose' => [],
            'credentials' => false,
            'max_age' => 600,
        ];

        return function ($request, callable $next) use ($options) {
            $origin = $request->header('Origin');

            if (is_null($origin) || !$this->isAllowed($origin, $options['origins'])) {
                return $next($request);
            }

            $headers = [
                'Access-Control-Allow-Origin' => '*' === $options['origins'] && !$options['credentials'] ? '*' : $origin,
                'Vary' => 'Origin',
            ];

            if ($options['credentials']) {
                $headers['Access-Control-Allow-Credentials'] = 'true';
            }

            // preflight request
            if ('OPTIONS' === $request->method() && !is_null($request->header('Access-Control-Request-Method'))) {
                $headers['Access-Control-Allow-Methods'] = implode(', ', (array) $options['methods']);
                $allowed = is_null($options['headers'])
                    ? $request->header('Access-Control-Request-Headers')
                    : implode(', ', (array) $options['headers']);

                if (!empty($allowed)) {
                    $headers['Access-Control-Allow-Headers'] = $allowed;
                }

                $headers['Access-Control-Max-Age'] = (string) $options['max_age'];

                return call_user_func($this->mf, 'response')->noContent($headers);
            }

            if (!empty($options['expose'])) {
                $headers['Access-Control-Expose-Headers'] = implode(', ', (array) $options['expose']);
            }

            $response = $next($request);

            foreach ($headers as $name => $value) {
                $response = 'Vary' === $name ? $response->withAddedHeader($name, $value) : $response->withHeader($name, $value);
            }

            return $response;
        };
    }

    /**
     * @param string          $origin  Request origin
     * @param string|string[] $allowed Allowed origins
     */
    private function isAllowed(string $origin, $allowed)
    {
        if ('*' === $allowed) {
            return true;
        }

        foreach ((array) $allowed as $pattern) {
            if ($pattern === $origin) {
                return true;
            }

            $regex = '#^'.str_replace('\*', '[a-z0-9-]+', preg_quote($pattern, '#')).'$#i';

            if (false !== strpos($pattern, '*') && preg_match($regex, $origin)) {
                return true;
            }
        }

        return false;
    }
};
