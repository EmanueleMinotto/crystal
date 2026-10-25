<?php

/**
 * HTTP responses, `$mf('response')` returns a new response each time.
 *
 * Status, headers and cookies are sent only by `send`, so they can be
 * changed at any time. Route callbacks can return a response, it's sent
 * by the router.
 */
$deps['response'] = function () {
    return new class () {
        /**
         * @var int
         */
        private $status = 200;

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
         */
        public function json($data, $status = null)
        {
            assert(is_null($status) || is_int($status));

            $json = json_encode($data);

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
