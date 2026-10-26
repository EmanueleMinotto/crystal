The `mailer` service composes and sends emails, with plain text, HTML and attachments, using `mail()` or an SMTP server.

```php
$mf('mailer')->setDriver('smtp', [
    'host' => 'smtp.example.com',
    'port' => 587,
    'username' => 'noreply@example.com',
    'password' => 'secret',
    'encryption' => 'tls',
]);
$mf('mailer')->setDefaults(['from' => ['noreply@example.com' => 'Example']]);

$mf->post('/password-reset', function ($request, $mailer) {
    $data = $request->validate(['email' => ['required', 'email']]);

    $mailer->send([
        'to' => $data['email'],
        'subject' => 'Reset your password',
        'text' => 'Open this link to choose a new password: https://example.com/reset?token=...',
    ]);

    echo 'Check your inbox!';
});
```

## Sending an Email

Messages are arrays, the mailer builds the MIME message and sends it with the configured driver:

```php
$mf('mailer')->send([
    'from' => ['noreply@example.com' => 'Example'],
    'to' => ['alice@example.com' => 'Alice', 'bob@example.com'],
    'cc' => 'carol@example.com',
    'bcc' => 'dave@example.com',
    'reply_to' => 'support@example.com',
    'subject' => 'Welcome!',
    'text' => 'Hello Alice',
    'html' => '<p>Hello <strong>Alice</strong></p>',
    'headers' => ['X-Mailer' => 'crystal'],
    'attachments' => [
        '/path/to/invoice.pdf',
        ['content' => 'foo,bar', 'name' => 'data.csv', 'type' => 'text/csv'],
    ],
]);
```

Supported keys:

* `from`: the sender, exactly one address is required
* `to`, `cc`, `bcc`, `reply_to`: an address (`'a@example.com'`), a list of addresses or an associative array of `address => name`, at least one recipient is required
* `subject`: non-ASCII subjects (and names) are encoded as described in [RFC 2047](https://www.rfc-editor.org/rfc/rfc2047)
* `text` and/or `html`: when both are defined, a `multipart/alternative` message is sent
* `headers`: additional headers, as `name => value`
* `attachments`: file paths or arrays with `path` (or `content` and `name`) and an optional `type` (`application/octet-stream` by default)

Addresses are validated with [`filter_var`](https://www.php.net/manual/en/function.filter-var.php) and line breaks in header values are rejected to prevent headers injection: in both cases an `InvalidArgumentException` is thrown.

`send` returns the success reported by the driver.

### Default Values

Values shared by every message (e.g. the sender) can be defined once:

```php
$mailer = $mf('mailer');

$mailer->setDefaults([
    'from' => ['noreply@example.com' => 'Example'],
]);

$mailer->send([
    'to' => 'alice@example.com',
    'subject' => 'Welcome!',
    'text' => 'Hello Alice',
]);
```

### Using Templates

The [[Template Engine]] can be used to render the body:

```php
$mf('mailer')->send([
    'to' => 'alice@example.com',
    'subject' => 'Welcome!',
    'text' => 'Hello Alice, welcome!',
    'html' => $mf('template')->render(__DIR__.'/templates/emails/welcome.html.php', ['name' => 'Alice']),
]);
```

## Choosing a Driver

Call `setDriver` before sending the first message. If no driver is set, the PHP `mail()` function is used by default.

### mail (default)

Uses the [`mail`](https://www.php.net/manual/en/function.mail.php) function, so it depends on the `sendmail_path` (or `SMTP` on Windows) configuration.

```php
$mailer = $mf('mailer');
$mailer->setDriver('mail');
```

### SMTP

Connects directly to an SMTP server, supporting `AUTH LOGIN` authentication and encryption (`ssl` for implicit TLS, `tls` for `STARTTLS`, both require the [OpenSSL](https://www.php.net/manual/en/book.openssl.php) extension).

```php
$mailer = $mf('mailer');
$mailer->setDriver('smtp', [
    'host' => 'smtp.example.com',
    'port' => 587,              // 25 by default
    'username' => 'user',       // optional
    'password' => 'secret',     // optional
    'encryption' => 'tls',      // null (default), 'ssl' or 'tls'
    'timeout' => 30,            // seconds, 30 by default
]);
```

SMTP errors are thrown as `RuntimeException`.

### Custom Driver

Any object with a `send` method can be used as a driver (e.g. to use an HTTP API or to store messages during tests):

```php
$mailer = $mf('mailer');
$mailer->setDriver(new class () {
    public function send(array $message)
    {
        // $message['from']:       sender address
        // $message['recipients']: list of addresses (to, cc and bcc)
        // $message['subject']:    original subject
        // $message['headers']:    encoded headers, as name => value (Bcc excluded)
        // $message['body']:       encoded MIME body

        return true;
    }
});
```

The same structure is returned by `$mailer->compose($message)`, useful to inspect a message without sending it.
