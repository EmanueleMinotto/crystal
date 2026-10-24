<?php

/**
 * Exposes a rate limiting middleware factory via $deps['ratelimit'],
 * counters are stored using the cache service (fixed window algorithm).
 *
 * @see https://www.rfc-editor.org/rfc/rfc6585#section-4 (429 Too Many Requests)
 */
$deps['ratelimit'] = new class ($this) {
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
     * Create the rate limiting middleware.
     *
     * Options:
     *   limit  — requests allowed in a window (default 60)
     *   window — window length in seconds (default 60)
     *   key    — `function ($request)` identifying the client (default: IP address)
     *   prefix — cache keys prefix (default "ratelimit")
     *
     * @return callable
     */
    public function middleware(array $options = [])
    {
        $options += [
            'limit' => 60,
            'window' => 60,
            'key' => null,
            'prefix' => 'ratelimit',
        ];

        return function ($request, callable $next) use ($options) {
            $id = is_callable($options['key']) ? call_user_func($options['key'], $request) : $request->ip();

            if (is_null($id)) {
                return $next($request);
            }

            $result = $this->hit((string) $id, $options['limit'], $options['window'], $options['prefix']);
            $headers = [
                'X-RateLimit-Limit' => (string) $options['limit'],
                'X-RateLimit-Remaining' => (string) $result['remaining'],
                'X-RateLimit-Reset' => (string) $result['reset'],
            ];

            if (!$result['allowed']) {
                throw call_user_func($this->mf, 'errors')->create(
                    429,
                    'Rate limit exceeded, retry later.',
                    [],
                    $headers + ['Retry-After' => (string) max(1, $result['reset'] - time())]
                );
            }

            $response = $next($request);

            foreach ($headers as $name => $value) {
                $response = $response->withHeader($name, $value);
            }

            return $response;
        };
    }

    /**
     * Count a request.
     *
     * @return array Keys: allowed, remaining, reset (UNIX timestamp)
     */
    public function hit(string $id, int $limit, int $window, string $prefix = 'ratelimit')
    {
        $cache = call_user_func($this->mf, 'cache');
        $start = (int) (floor(time() / $window) * $window);
        $key = $prefix.'.'.sha1($id).'.'.$start;
        $count = (int) $cache->get($key, 0) + 1;

        $cache->set($key, $count, $window);

        return [
            'allowed' => $count <= $limit,
            'remaining' => max(0, $limit - $count),
            'reset' => $start + $window,
        ];
    }
};
