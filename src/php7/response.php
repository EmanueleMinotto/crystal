<?php

/**
 * HTTP responses, `$mf('response')` returns a new response each time.
 *
 * Status, headers and cookies are sent only by `send`, so they can be
 * changed at any time. Route callbacks can return a response, it's sent
 * by the router.
 *
 * The `json_flags` setting contains the default flags of `json_encode()`.
 */
$deps['response'] = function () use (&$deps) {
    return new class ($deps) {
        /**
         * Dynamic set of dependencies, used to retrieve the settings.
         *
         * @var array
         */
        private $deps;

        /**
         * @var int
         */
        private $status = 200;

        /**
         * Entity tag, true when it's the hash of the body.
         *
         * @var string|bool|null
         */
        private $etag;

        /**
         * Whether the entity tag is weak.
         *
         * @var bool
         */
        private $weak = false;

        /**
         * Headers, lowercase name => [name, value].
         *
         * @var array
         */
        private $headers = [];

        /**
         * Cookies, name => [value, options].
         *
         * @var array
         */
        private $cookies = [];

        /**
         * @var string
         */
        private $body = '';

        /**
         * @param array $deps Dynamic set of dependencies.
         */
        public function __construct(array &$deps = [])
        {
            $this->deps = &$deps;
        }

        public function status(): int
        {
            return $this->status;
        }

        public function setStatus(int $status)
        {
            $this->status = $status;

            return $this;
        }

        /**
         * Response header, names are case insensitive.
         *
         * @return string|null
         */
        public function header(string $name)
        {
            $key = strtolower($name);

            return isset($this->headers[$key]) ? $this->headers[$key][1] : null;
        }

        /**
         * All the headers, name => value.
         */
        public function headers(): array
        {
            return array_column($this->headers, 1, 0);
        }

        public function setHeader(string $name, string $value)
        {
            $this->headers[strtolower($name)] = [$name, $value];

            return $this;
        }

        public function removeHeader(string $name)
        {
            unset($this->headers[strtolower($name)]);

            return $this;
        }

        /**
         * Add a cookie, options are the ones of setcookie().
         *
         * By default cookies are `httponly`, `samesite=Lax`, `secure` on HTTPS
         * and valid for the whole domain.
         *
         * @link https://www.php.net/manual/en/function.setcookie.php
         */
        public function cookie(string $name, string $value, array $options = [])
        {
            $this->cookies[$name] = [$value, $options + [
                'expires' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => !empty($_SERVER['HTTPS']) && 'off' !== strtolower($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]];

            return $this;
        }

        /**
         * Remove a cookie from the client.
         */
        public function forgetCookie(string $name, array $options = [])
        {
            return $this->cookie($name, '', ['expires' => time() - 3600] + $options);
        }

        /**
         * Cookies, name => [value, options].
         */
        public function cookies(): array
        {
            return $this->cookies;
        }

        public function body(): string
        {
            return $this->body;
        }

        public function setBody(string $body)
        {
            $this->body = $body;

            return $this;
        }

        /**
         * Append content to the body.
         */
        public function write(string $content)
        {
            $this->body .= $content;

            return $this;
        }

        /**
         * JSON body, with the Content-Type header.
         *
         * @param mixed    $data   Data to encode
         * @param int|null $status Status code, unchanged when null
         * @param int|null $flags  Flags of json_encode(), the `json_flags` setting (or 0) when null
         *
         * @link https://www.php.net/manual/en/json.constants.php
         */
        public function json($data, $status = null, $flags = null)
        {
            assert(is_null($status) || is_int($status));
            assert(is_null($flags) || is_int($flags));

            if (is_null($flags)) {
                $flags = is_int($this->deps['json_flags'] ?? null) ? $this->deps['json_flags'] : 0;
            }

            $json = json_encode($data, $flags);

            if (false === $json) {
                throw new InvalidArgumentException('Unable to encode the JSON body: '.json_last_error_msg());
            }

            if (!is_null($status)) {
                $this->status = $status;
            }

            $this->setHeader('Content-Type', 'application/json');

            return $this->setBody($json);
        }

        /**
         * 201 response, with the `Location` header of the created resource and its JSON representation.
         *
         * @param mixed  $data     Data to encode, the body is empty when null
         * @param string $location URL of the created resource
         */
        public function created($data = null, string $location = '')
        {
            if ('' !== $location) {
                $this->setHeader('Location', $location);
            }

            return is_null($data) ? $this->setStatus(201) : $this->json($data, 201);
        }

        /**
         * 202 response, the request will be processed later.
         *
         * @param mixed $data Data to encode, the body is empty when null
         */
        public function accepted($data = null)
        {
            return is_null($data) ? $this->setStatus(202) : $this->json($data, 202);
        }

        /**
         * 204 response, without body.
         */
        public function noContent()
        {
            $this->removeHeader('Content-Type');

            return $this->setStatus(204)->setBody('');
        }

        /**
         * Set the entity tag (`ETag` header), GET and HEAD requests with a matching
         * `If-None-Match` header receive a 304 response without body.
         *
         * @param string|null $etag Entity tag without quotes, the hash of the body when it's sent when null
         * @param bool        $weak Whether the tag is weak (`W/` prefix)
         */
        public function setEtag($etag = null, bool $weak = false)
        {
            assert(is_null($etag) || is_string($etag));

            $this->etag = is_null($etag) ? true : $etag;
            $this->weak = $weak;

            return $this;
        }

        /**
         * Entity tag, as sent in the `ETag` header, null when not set.
         *
         * @return string|null
         */
        public function etag()
        {
            if (is_null($this->etag)) {
                return null;
            }

            $etag = true === $this->etag ? sha1($this->body) : $this->etag;

            return ($this->weak ? 'W/' : '').'"'.str_replace('"', '', $etag).'"';
        }

        /**
         * Whether the client already has this response: the status is 2xx, the request
         * is GET or HEAD and its `If-None-Match` header matches the entity tag (weak comparison).
         */
        public function isNotModified(): bool
        {
            $etag = $this->etag();
            $header = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;

            // only successful responses can be replaced by a 304
            if (is_null($etag) || is_null($header) || $this->status < 200 || $this->status > 299) {
                return false;
            }

            if (!in_array(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), ['GET', 'HEAD'], true)) {
                return false;
            }

            if ('*' === trim($header)) {
                return true;
            }

            $strip = function ($tag) {
                $tag = trim($tag);

                return 0 === strpos($tag, 'W/') ? substr($tag, 2) : $tag;
            };

            return in_array($strip($etag), array_map($strip, explode(',', $header)), true);
        }

        /**
         * Redirect to another URL.
         */
        public function redirect(string $url, int $status = 302)
        {
            $this->status = $status;

            return $this->setHeader('Location', $url);
        }

        /**
         * Send status, headers, cookies and body.
         */
        public function send()
        {
            if (!is_null($this->etag)) {
                $notModified = $this->isNotModified();
                $this->setHeader('ETag', $this->etag());

                if ($notModified) {
                    // headers describing the body aren't sent
                    $this->status = 304;
                    $this->body = '';
                    $this->removeHeader('Content-Type')->removeHeader('Content-Length');
                }
            }

            http_response_code($this->status);

            foreach ($this->headers as $header) {
                header($header[0].': '.$header[1]);
            }

            foreach ($this->cookies as $name => $cookie) {
                list($value, $options) = $cookie;

                if (PHP_VERSION_ID >= 70300) {
                    setcookie($name, $value, $options);
                } else {
                    // samesite isn't supported before PHP 7.3
                    setcookie($name, $value, $options['expires'], $options['path'], $options['domain'], $options['secure'], $options['httponly']);
                }
            }

            echo $this->body;
        }
    };
};
