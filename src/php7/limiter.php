<?php

/**
 * Rate limiting, the hits are counted in fixed windows using the cache service.
 *
 * Counters are read and written without locks, so with concurrent requests
 * the limits are approximate.
 */
$deps['limiter'] = new class ($deps) {
    /**
     * Dynamic set of dependencies, used to retrieve the cache, request, response and errors services.
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
     * Count a hit when the limit isn't reached.
     *
     * @param string $key     Identifier of the counter, i.e. an IP address or a user id
     * @param int    $max     Hits allowed in the window
     * @param int    $seconds Duration of the window
     *
     * @return bool Whether the hit is allowed
     */
    public function attempt(string $key, int $max, int $seconds): bool
    {
        if ($this->tooManyAttempts($key, $max)) {
            return false;
        }

        $this->hit($key, $seconds);

        return true;
    }

    /**
     * Count a hit, the window starts with the first one.
     *
     * @return int Hits in the current window
     */
    public function hit(string $key, int $seconds): int
    {
        $hits = $this->read($key);

        if (is_null($hits)) {
            $hits = [
                'count' => 0,
                'reset' => time() + max(1, $seconds),
            ];
        }

        ++$hits['count'];
        $this->service('cache')->set($this->cacheKey($key), $hits, max(1, $hits['reset'] - time()));

        return $hits['count'];
    }

    /**
     * Hits in the current window.
     */
    public function hits(string $key): int
    {
        $hits = $this->read($key);

        return is_null($hits) ? 0 : $hits['count'];
    }

    public function tooManyAttempts(string $key, int $max): bool
    {
        return $this->hits($key) >= $max;
    }

    /**
     * Hits still allowed in the current window.
     */
    public function remaining(string $key, int $max): int
    {
        return max(0, $max - $this->hits($key));
    }

    /**
     * Seconds before the current window ends, 0 when there are no hits.
     */
    public function availableIn(string $key): int
    {
        $hits = $this->read($key);

        return is_null($hits) ? 0 : max(0, $hits['reset'] - time());
    }

    /**
     * Reset the hits.
     */
    public function clear(string $key)
    {
        $this->service('cache')->delete($this->cacheKey($key));
    }

    /**
     * Middleware limiting the requests, a 429 response is returned when the limit is reached.
     *
     * Counters are shared by the routes using the same key: by default the
     * IP address, the callback receives the request and returns the key.
     *
     * @param int           $max     Requests allowed in the window
     * @param int           $seconds Duration of the window
     * @param callable|null $key     Callback returning the key of the request
     *
     * @return callable
     */
    public function middleware(int $max = 60, int $seconds = 60, $key = null)
    {
        return function ($request, callable $next) use ($max, $seconds, $key) {
            $key = is_null($key) ? (string) $request->ip() : (string) call_user_func($key, $request);

            if (!$this->attempt($key, $max, $seconds)) {
                $retry = max(1, $this->availableIn($key));
                $errors = $this->deps['errors'] ?? null;

                if (is_object($errors) && method_exists($errors, 'response')) {
                    $response = $errors->response(429);
                } else {
                    $response = $this->service('response')->setStatus(429)->setBody('Too Many Requests');
                }

                return $response
                    ->setHeader('Retry-After', (string) $retry)
                    ->setHeader('X-RateLimit-Limit', (string) $max)
                    ->setHeader('X-RateLimit-Remaining', '0');
            }

            return $next()
                ->setHeader('X-RateLimit-Limit', (string) $max)
                ->setHeader('X-RateLimit-Remaining', (string) $this->remaining($key, $max));
        };
    }

    /**
     * Hits of the current window, null when it has ended.
     *
     * @return array|null
     */
    private function read(string $key)
    {
        $hits = $this->service('cache')->get($this->cacheKey($key));

        return is_array($hits) && $hits['reset'] > time() ? $hits : null;
    }

    /**
     * Keys are hashed, cache keys allow only some characters.
     */
    private function cacheKey(string $key): string
    {
        return 'crystal_limiter.'.sha1($key);
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
