<?php

/**
 * Exposes a mailer object via $deps['mailer'] to compose and send emails.
 *
 * Messages are arrays supporting the keys:
 * from, to, cc, bcc, reply_to, subject, text, html, headers, attachments.
 *
 * Drivers: mail (default), smtp, custom.
 *
 * @see https://www.php.net/manual/en/function.mail.php
 * @see https://www.rfc-editor.org/rfc/rfc5321 (SMTP)
 * @see https://www.rfc-editor.org/rfc/rfc2045 (MIME)
 */
$deps['mailer'] = new class () {
    /**
     * Active driver instance.
     *
     * @var object|null
     */
    private $driver;

    /**
     * Values merged into every message (e.g. a default "from").
     *
     * @var array
     */
    private $defaults = [];

    /**
     * Configure the mailer driver.
     *
     * Built-in drivers:
     *   setDriver('mail')          — PHP mail() function (default)
     *   setDriver('smtp', $config) — SMTP server, $config keys:
     *                                host, port, username, password,
     *                                encryption (null|'ssl'|'tls'), timeout
     *
     * Custom driver (must implement send):
     *   setDriver($customObject)
     *
     * Drivers receive the composed message as an array with the keys:
     * from, recipients, subject, headers and body.
     *
     * @param  string|object             $driver Driver name or custom driver object
     * @param  mixed                     $config Driver-specific configuration
     * @throws \InvalidArgumentException If driver is unknown or configuration is invalid
     * @throws \RuntimeException         If driver cannot be initialised
     */
    public function setDriver($driver, $config = null)
    {
        if (is_object($driver)) {
            if (!method_exists($driver, 'send')) {
                throw new \InvalidArgumentException('Custom driver is missing method: send');
            }

            $this->driver = $driver;

            return;
        }

        switch ($driver) {
            case 'mail':
                if (!function_exists('mail')) {
                    throw new \RuntimeException('The mail() function is not available');
                }

                $this->driver = new class () {
                    public function send(array $message)
                    {
                        $headers = $message['headers'];
                        $to = $headers['To'] ?? 'undisclosed-recipients:;';
                        $subject = $headers['Subject'];

                        // mail() adds "To" and "Subject" by itself
                        unset($headers['To'], $headers['Subject']);

                        $raw = '';

                        foreach ($headers as $name => $value) {
                            $raw .= $name.': '.$value."\r\n";
                        }

                        // the envelope sender is required by some MTAs to avoid spoofing warnings
                        return mail($to, $subject, $message['body'], rtrim($raw), '-f'.$message['from']);
                    }
                };

                break;

            case 'smtp':
                if (!is_array($config) || empty($config['host'])) {
                    throw new \InvalidArgumentException(
                        'smtp driver requires an array with at least the "host" key as second argument'
                    );
                }

                $config = array_merge([
                    'port' => 25,
                    'username' => null,
                    'password' => null,
                    'encryption' => null,
                    'timeout' => 30,
                ], $config);

                if (!in_array($config['encryption'], [null, 'ssl', 'tls'], true)) {
                    throw new \InvalidArgumentException('Unknown SMTP encryption: '.$config['encryption']);
                }

                if (null !== $config['encryption'] && !extension_loaded('openssl')) {
                    throw new \RuntimeException('The openssl extension is required for SMTP encryption');
                }

                $this->driver = new class ($config) {
                    private $config;

                    private $socket;

                    public function __construct(array $config)
                    {
                        $this->config = $config;
                    }

                    public function send(array $message)
                    {
                        $config = $this->config;
                        $transport = 'ssl' === $config['encryption'] ? 'ssl' : 'tcp';

                        $this->socket = @stream_socket_client(
                            $transport.'://'.$config['host'].':'.$config['port'],
                            $errno,
                            $errstr,
                            $config['timeout']
                        );

                        if (false === $this->socket) {
                            throw new \RuntimeException(sprintf('SMTP connection failed: %s (%d)', $errstr, $errno));
                        }

                        stream_set_timeout($this->socket, $config['timeout']);

                        try {
                            $this->expect(220);

                            // the domain of the sender identifies the client
                            $client = substr(strrchr($message['from'], '@'), 1);
                            $this->command('EHLO '.$client, 250);

                            if ('tls' === $config['encryption']) {
                                $this->command('STARTTLS', 220);

                                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                                    throw new \RuntimeException('SMTP STARTTLS negotiation failed');
                                }

                                // capabilities must be requested again after the TLS handshake
                                $this->command('EHLO '.$client, 250);
                            }

                            if (null !== $config['username']) {
                                $this->command('AUTH LOGIN', 334);
                                $this->command(base64_encode($config['username']), 334);
                                $this->command(base64_encode((string) $config['password']), 235);
                            }

                            $this->command('MAIL FROM:<'.$message['from'].'>', 250);

                            foreach ($message['recipients'] as $recipient) {
                                $this->command('RCPT TO:<'.$recipient.'>', [250, 251]);
                            }

                            $this->command('DATA', 354);

                            $raw = '';

                            foreach ($message['headers'] as $name => $value) {
                                $raw .= $name.': '.$value."\r\n";
                            }

                            $raw .= "\r\n".$message['body'];

                            // dot-stuffing, lines starting with a dot must be escaped
                            // https://www.rfc-editor.org/rfc/rfc5321#section-4.5.2
                            $raw = preg_replace('/^\./m', '..', $raw);

                            $this->command($raw."\r\n.", 250);
                            $this->command('QUIT', 221);
                        } finally {
                            fclose($this->socket);
                        }

                        return true;
                    }

                    /**
                     * Write a command and check the response code.
                     *
                     * @param string    $command
                     * @param int|int[] $expected
                     */
                    private function command(string $command, $expected)
                    {
                        fwrite($this->socket, $command."\r\n");

                        return $this->expect($expected);
                    }

                    /**
                     * Read a (multiline) response and check its code.
                     *
                     * @param int|int[] $expected
                     *
                     * @throws \RuntimeException If the code isn't the expected one
                     *
                     * @return string
                     */
                    private function expect($expected)
                    {
                        $response = '';

                        // multiline responses use a dash after the code, e.g. "250-SIZE"
                        while (false !== ($line = fgets($this->socket, 515))) {
                            $response .= $line;

                            if (!isset($line[3]) || '-' !== $line[3]) {
                                break;
                            }
                        }

                        if (!in_array((int) substr($response, 0, 3), (array) $expected, true)) {
                            throw new \RuntimeException('Unexpected SMTP response: '.trim($response));
                        }

                        return $response;
                    }
                };

                break;

            default:
                throw new \InvalidArgumentException("Unknown mailer driver: $driver");
        }
    }

    /**
     * Set values merged into every message, e.g. ['from' => 'noreply@example.com'].
     *
     * @param array $defaults
     */
    public function setDefaults(array $defaults)
    {
        $this->defaults = $defaults;
    }

    /**
     * Compose and send a message.
     *
     * Addresses (from, to, cc, bcc, reply_to) can be a string ('a@example.com'),
     * a list of strings or an associative array (['a@example.com' => 'Name']).
     * Attachments can be file paths or arrays with the keys
     * path or content, name and type.
     *
     * @param  array                     $message
     * @return bool                      Success, as reported by the driver
     * @throws \InvalidArgumentException If the message is invalid
     */
    public function send(array $message)
    {
        if (null === $this->driver) {
            $this->setDriver('mail');
        }

        return (bool) $this->driver->send($this->compose($message));
    }

    /**
     * Build the message headers and the MIME body without sending it.
     *
     * @param  array                     $message
     * @return array                     from, recipients, subject, headers and body
     * @throws \InvalidArgumentException If the message is invalid
     */
    public function compose(array $message)
    {
        $message = array_merge([
            'from' => null,
            'to' => [],
            'cc' => [],
            'bcc' => [],
            'reply_to' => [],
            'subject' => '',
            'text' => null,
            'html' => null,
            'headers' => [],
            'attachments' => [],
        ], $this->defaults, $message);

        // normalizes addresses to email => name, rejecting invalid ones
        $normalize = function ($addresses, string $field) {
            $result = [];

            foreach ((array) $addresses as $key => $value) {
                list($email, $name) = is_string($key) ? [$key, (string) $value] : [(string) $value, ''];

                if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new \InvalidArgumentException(sprintf('Invalid "%s" address: %s', $field, $email));
                }

                $result[$email] = $name;
            }

            return $result;
        };

        // header values containing line breaks would allow headers injection
        $safe = function ($value, string $field) {
            if (preg_match('/[\r\n]/', (string) $value)) {
                throw new \InvalidArgumentException(sprintf('Invalid line break in "%s"', $field));
            }

            return (string) $value;
        };

        // non-ASCII values are encoded as described in RFC 2047
        $encode = function (string $value) {
            return preg_match('/[^\x20-\x7e]/', $value)
                ? '=?UTF-8?B?'.base64_encode($value).'?='
                : $value;
        };

        $format = function (array $addresses) use ($encode, $safe) {
            $formatted = [];

            foreach ($addresses as $email => $name) {
                $name = $safe($name, 'name');
                $formatted[] = '' === $name
                    ? $email
                    : (preg_match('/[^\x20-\x7e]/', $name) ? $encode($name) : '"'.addcslashes($name, '"\\').'"').' <'.$email.'>';
            }

            return implode(', ', $formatted);
        };

        $from = $normalize($message['from'], 'from');

        if (1 !== count($from)) {
            throw new \InvalidArgumentException('Exactly one "from" address is required');
        }

        $to = $normalize($message['to'], 'to');
        $cc = $normalize($message['cc'], 'cc');
        $bcc = $normalize($message['bcc'], 'bcc');
        $replyTo = $normalize($message['reply_to'], 'reply_to');
        $recipients = array_keys(array_merge($to, $cc, $bcc));

        if (empty($recipients)) {
            throw new \InvalidArgumentException('At least one recipient is required');
        }

        if (null === $message['text'] && null === $message['html']) {
            throw new \InvalidArgumentException('Message requires a "text" or "html" body');
        }

        $sender = key($from);

        $headers = [
            'Date' => date('r'),
            'From' => $format($from),
        ];

        if ($to) {
            $headers['To'] = $format($to);
        }

        if ($cc) {
            $headers['Cc'] = $format($cc);
        }

        if ($replyTo) {
            $headers['Reply-To'] = $format($replyTo);
        }

        $headers['Subject'] = $encode($safe($message['subject'], 'subject'));
        $headers['Message-ID'] = sprintf('<%s@%s>', bin2hex(random_bytes(16)), substr(strrchr($sender, '@'), 1));
        $headers['MIME-Version'] = '1.0';

        foreach ($message['headers'] as $name => $value) {
            if (!preg_match('/^[!-9;-~]+$/', $name)) {
                throw new \InvalidArgumentException(sprintf('Invalid header name: %s', $name));
            }

            $headers[$name] = $safe($value, $name);
        }

        // each part is a [headers, body] pair, bodies use CRLF line endings
        $part = function (string $type, string $content) {
            $content = preg_replace('/\r\n?|\n/', "\r\n", $content);

            return [
                'Content-Type: '.$type.'; charset=UTF-8'."\r\n".'Content-Transfer-Encoding: quoted-printable',
                quoted_printable_encode($content),
            ];
        };

        $multipart = function (string $subtype, array $parts) {
            $boundary = '=_'.bin2hex(random_bytes(12));
            $body = '';

            foreach ($parts as $item) {
                $body .= '--'.$boundary."\r\n".$item[0]."\r\n\r\n".$item[1]."\r\n";
            }

            return [
                'Content-Type: multipart/'.$subtype.'; boundary="'.$boundary.'"',
                $body.'--'.$boundary.'--',
            ];
        };

        $alternatives = [];

        if (null !== $message['text']) {
            $alternatives[] = $part('text/plain', (string) $message['text']);
        }

        if (null !== $message['html']) {
            $alternatives[] = $part('text/html', (string) $message['html']);
        }

        $content = 1 === count($alternatives) ? $alternatives[0] : $multipart('alternative', $alternatives);

        if (!empty($message['attachments'])) {
            $parts = [$content];

            foreach ((array) $message['attachments'] as $attachment) {
                if (is_string($attachment)) {
                    $attachment = ['path' => $attachment];
                }

                if (isset($attachment['path'])) {
                    if (!is_file($attachment['path']) || !is_readable($attachment['path'])) {
                        throw new \InvalidArgumentException(sprintf('Attachment not readable: %s', $attachment['path']));
                    }

                    $attachment['content'] = file_get_contents($attachment['path']);
                    $attachment['name'] = $attachment['name'] ?? basename($attachment['path']);
                }

                if (!isset($attachment['content'], $attachment['name'])) {
                    throw new \InvalidArgumentException('Attachments require a "path" or both "content" and "name"');
                }

                $name = $encode($safe($attachment['name'], 'attachment name'));
                $type = $safe($attachment['type'] ?? 'application/octet-stream', 'attachment type');

                $parts[] = [
                    'Content-Type: '.$type.'; name="'.$name.'"'."\r\n"
                    .'Content-Transfer-Encoding: base64'."\r\n"
                    .'Content-Disposition: attachment; filename="'.$name.'"',
                    rtrim(chunk_split(base64_encode($attachment['content']), 76, "\r\n")),
                ];
            }

            $content = $multipart('mixed', $parts);
        }

        // the content headers of the root part become message headers
        foreach (explode("\r\n", $content[0]) as $line) {
            list($name, $value) = explode(': ', $line, 2);
            $headers[$name] = $value;
        }

        return [
            'from' => $sender,
            'recipients' => $recipients,
            'subject' => (string) $message['subject'],
            'headers' => $headers,
            'body' => $content[1],
        ];
    }
};
