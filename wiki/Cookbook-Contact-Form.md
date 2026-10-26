A contact form: the errors are shown next to the fields, valid messages are sent by email and each visitor can send at most 3 messages per hour.
During development the emails are saved as `.eml` files instead of being sent.

```
contact-form/
├── templates/
│   └── contact.html.php
├── crystal.php
└── index.php
```

`index.php`:

```php
<?php

$mf = require __DIR__.'/crystal.php';

$mf('errors')->enable();

// the rate limiter counts the messages in the cache, shared between requests
is_dir(__DIR__.'/cache') || mkdir(__DIR__.'/cache');
$mf('cache')::setDriver('filename', __DIR__.'/cache');

// SMTP when it's configured, otherwise the emails are saved in the "mails" directory
if (getenv('SMTP_HOST')) {
    $mf('mailer')->setDriver('smtp', [
        'host' => getenv('SMTP_HOST'),
        'port' => 587,
        'username' => getenv('SMTP_USERNAME'),
        'password' => getenv('SMTP_PASSWORD'),
        'encryption' => 'tls',
    ]);
} else {
    $mf('mailer')->setDriver(new class () {
        public function send(array $message)
        {
            is_dir(__DIR__.'/mails') || mkdir(__DIR__.'/mails');

            $headers = '';

            foreach ($message['headers'] as $name => $value) {
                $headers .= $name.': '.$value."\r\n";
            }

            return false !== file_put_contents(__DIR__.'/mails/'.uniqid().'.eml', $headers."\r\n".$message['body']);
        }
    });
}

$mf('mailer')->setDefaults([
    'from' => ['website@example.com' => 'My website'],
    'to' => 'owner@example.com',
]);

$mf->get('/contact', function ($request, $template) {
    echo $template->render(__DIR__.'/templates/contact.html.php', [
        'sent' => (bool) $request->query('sent'),
        'errors' => [],
        'old' => [],
    ]);
});

$mf->post('/contact', function ($request, $response, $validator, $template, $limiter, $mailer, $errors) {
    $invalid = $validator->check($request->input(), [
        'name' => ['required', 'string', 'max' => 100],
        'email' => ['required', 'email'],
        'message' => ['required', 'string', 'between' => [10, 2000]],
    ]);

    // the form again, with the errors and the values sent
    if (!empty($invalid)) {
        return $response->setStatus(422)->setBody($template->render(__DIR__.'/templates/contact.html.php', [
            'sent' => false,
            'errors' => $invalid,
            'old' => $request->input(),
        ]));
    }

    // 3 messages per hour for each IP address
    if (!$limiter->attempt('contact:'.$request->ip(), 3, 3600)) {
        $errors->abort(429, 'Too many messages, please retry later.');
    }

    $mailer->send([
        'reply_to' => [$request->input('email') => $request->input('name')],
        'subject' => 'Message from '.$request->input('name'),
        'text' => $request->input('message'),
    ]);

    // a redirect, so reloading the page doesn't send the message again
    return $response->redirect('/contact?sent=1');
});
```

`templates/contact.html.php`:

```html
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Contact us</title>
    </head>
    <body>
        <h1>Contact us</h1>

        <?php if ($sent): ?>
            <p class="notice">Thank you, we'll answer soon!</p>
        <?php endif; ?>

        <form method="post" action="/contact">
            <p>
                <label>Name <input name="name" value="<?php echo $this->e($old['name'] ?? ''); ?>"></label>
                <?php foreach ($errors['name'] ?? [] as $error): ?>
                    <span class="error"><?php echo $this->e($error); ?></span>
                <?php endforeach; ?>
            </p>
            <p>
                <label>Email <input name="email" type="email" value="<?php echo $this->e($old['email'] ?? ''); ?>"></label>
                <?php foreach ($errors['email'] ?? [] as $error): ?>
                    <span class="error"><?php echo $this->e($error); ?></span>
                <?php endforeach; ?>
            </p>
            <p>
                <label>Message <textarea name="message"><?php echo $this->e($old['message'] ?? ''); ?></textarea></label>
                <?php foreach ($errors['message'] ?? [] as $error): ?>
                    <span class="error"><?php echo $this->e($error); ?></span>
                <?php endforeach; ?>
            </p>
            <button>Send</button>
        </form>
    </body>
</html>
```

## How it works

-   `check()` returns the errors of each field instead of stopping the request (see [[Validation]]), so the form is shown again with the values sent
-   the [[Rate Limiting]] counts only the messages sent, invalid forms don't count
-   after sending, the browser is redirected to `/contact?sent=1` ([Post/Redirect/Get](https://en.wikipedia.org/wiki/Post/Redirect/Get)): reloading the page doesn't send the message again
-   the custom driver of the [[Mailer]] saves each email as an `.eml` file, which email clients can open; with the `SMTP_HOST`, `SMTP_USERNAME` and `SMTP_PASSWORD` environment variables the emails are sent (see [[Configuration]] for `.env` files)

A submission with errors:

```bash
$ curl -X POST localhost:8080/contact -d 'name=Bob&email=bob&message=Hi'
```

```
Contact us
Name [Bob]
Email [bob]    The email field must be a valid email address.
Message [Hi]   The message field must be between 10 and 2000.
```

## Going further

-   add a hidden field that people don't fill but bots do, and ignore the messages that have it
-   protect the form with a [[CSRF]] token, when the form is used by logged in users
