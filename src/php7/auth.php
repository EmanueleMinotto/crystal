<?php

/**
 * Exposes authentication helpers via $deps['auth']:
 * - JSON Web Tokens encoding and decoding (HS256, HS384, HS512)
 * - Bearer token and API key middlewares
 *
 * The authenticated identity is stored in the `user` request attribute.
 *
 * @see https://www.rfc-editor.org/rfc/rfc7519 (JWT)
 * @see https://www.rfc-editor.org/rfc/rfc6750 (Bearer Token Usage)
 */
$deps['auth'] = new class ($this) {
    /**
     * Framework instance, used to resolve other services.
     *
     * @var callable
     */
    private $mf;

    /**
     * Supported algorithms.
     *
     * @var array
     */
    private $algorithms = [
        'HS256' => 'sha256',
        'HS384' => 'sha384',
        'HS512' => 'sha512',
    ];

    public function __construct(callable $mf)
    {
        $this->mf = $mf;
    }

    /**
     * Encode a JWT.
     *
     * @param array  $claims    Payload, `iat` is added if missing
     * @param string $secret    Shared secret
     * @param int    $ttl       Seconds before expiration, 0 to skip the `exp` claim
     * @param string $algorithm One of HS256, HS384, HS512
     *
     * @return string
     */
    public function encode(array $claims, string $secret, int $ttl = 3600, string $algorithm = 'HS256')
    {
        if (!isset($this->algorithms[$algorithm])) {
            throw new InvalidArgumentException(sprintf('Unsupported algorithm "%s"', $algorithm));
        }

        $claims += ['iat' => time()];

        if ($ttl > 0) {
            $claims += ['exp' => $claims['iat'] + $ttl];
        }

        $segments = [
            $this->base64(json_encode(['typ' => 'JWT', 'alg' => $algorithm])),
            $this->base64(json_encode($claims)),
        ];
        $segments[] = $this->base64(hash_hmac($this->algorithms[$algorithm], implode('.', $segments), $secret, true));

        return implode('.', $segments);
    }

    /**
     * Decode and verify a JWT.
     *
     * @param string   $token      Encoded token
     * @param string   $secret     Shared secret
     * @param string[] $algorithms Accepted algorithms
     * @param int      $leeway     Seconds of tolerance for time based claims
     *
     * @throws UnexpectedValueException If the token is invalid or expired
     *
     * @return array Claims
     */
    public function decode(string $token, string $secret, array $algorithms = ['HS256'], int $leeway = 0)
    {
        $segments = explode('.', $token);

        if (3 !== count($segments)) {
            throw new UnexpectedValueException('Wrong number of segments');
        }

        $header = json_decode($this->unbase64($segments[0]), true);
        $claims = json_decode($this->unbase64($segments[1]), true);

        if (!is_array($header) || !is_array($claims)) {
            throw new UnexpectedValueException('Malformed token');
        }

        $algorithm = $header['alg'] ?? null;

        if (!in_array($algorithm, $algorithms, true) || !isset($this->algorithms[$algorithm])) {
            throw new UnexpectedValueException('Algorithm not allowed');
        }

        $signature = hash_hmac($this->algorithms[$algorithm], $segments[0].'.'.$segments[1], $secret, true);

        if (!hash_equals($signature, $this->unbase64($segments[2]))) {
            throw new UnexpectedValueException('Signature verification failed');
        }

        $now = time();

        if (isset($claims['nbf']) && $claims['nbf'] > $now + $leeway) {
            throw new UnexpectedValueException('Token not valid yet');
        }

        if (isset($claims['exp']) && $claims['exp'] <= $now - $leeway) {
            throw new UnexpectedValueException('Token expired');
        }

        return $claims;
    }

    /**
     * Middleware requiring a Bearer token.
     *
     * @param string|Closure $verifier JWT secret, or `function ($token, $request)`
     *                                 returning the user or null
     *
     * @return callable
     */
    public function bearer($verifier, array $algorithms = ['HS256'])
    {
        return function ($request, callable $next) use ($verifier, $algorithms) {
            $token = $request->bearerToken();

            if (is_null($token)) {
                throw $this->unauthorized('Bearer', 'Missing bearer token.');
            }

            if (is_object($verifier) && is_callable($verifier)) {
                $user = call_user_func($verifier, $token, $request);
            } else {
                try {
                    $user = $this->decode($token, $verifier, $algorithms);
                } catch (UnexpectedValueException $exception) {
                    throw $this->unauthorized('Bearer error="invalid_token"', $exception->getMessage().'.');
                }
            }

            if (is_null($user) || false === $user) {
                throw $this->unauthorized('Bearer error="invalid_token"', 'Invalid bearer token.');
            }

            $request->setAttribute('user', $user);

            return $next($request);
        };
    }

    /**
     * Middleware requiring an API key.
     *
     * @param string[]|Closure $keys   Valid keys indexed by user (or a list),
     *                                 or `function ($key, $request)` returning the user or null
     * @param string           $header Header containing the key
     *
     * @return callable
     */
    public function apiKey($keys, string $header = 'X-API-Key')
    {
        return function ($request, callable $next) use ($keys, $header) {
            $key = $request->header($header);
            $user = null;

            if (!is_null($key) && is_object($keys) && is_callable($keys)) {
                $user = call_user_func($keys, $key, $request);
            } elseif (!is_null($key)) {
                foreach ((array) $keys as $name => $valid) {
                    if (hash_equals((string) $valid, $key)) {
                        $user = is_int($name) ? $key : $name;

                        break;
                    }
                }
            }

            if (is_null($user) || false === $user) {
                throw $this->unauthorized('ApiKey header="'.$header.'"', is_null($key) ? 'Missing API key.' : 'Invalid API key.');
            }

            $request->setAttribute('user', $user);

            return $next($request);
        };
    }

    private function unauthorized(string $challenge, string $detail)
    {
        return call_user_func($this->mf, 'errors')->create(401, $detail, [], ['WWW-Authenticate' => $challenge]);
    }

    private function base64(string $data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function unbase64(string $data)
    {
        return (string) base64_decode(strtr($data, '-_', '+/'), true);
    }
};
