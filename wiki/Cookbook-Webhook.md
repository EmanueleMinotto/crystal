An endpoint receiving the webhooks of a payment provider: the signature of each request is verified, the events are validated, logged to a file and processed only once, even when the provider sends them again.

```
webhooks/
├── crystal.php
└── index.php
```

`index.php`:

```php
<?php

$mf = require __DIR__.'/crystal.php';

$mf('errors')->enable(false, ['json' => '.*']);

// the secret shared with the provider, from the environment
$mf('webhook_secret', getenv('WEBHOOK_SECRET') ?: 'change-me');

$mf('database')::setPdo(new PDO('sqlite:'.__DIR__.'/webhooks.sqlite'));
$mf('database')->query('CREATE TABLE IF NOT EXISTS events (id TEXT PRIMARY KEY, type TEXT NOT NULL, received_at INTEGER NOT NULL)');

$mf('logger')->setImplementation(function ($level, $message, $context) {
    file_put_contents(__DIR__.'/webhooks.log', sprintf("%s: %s\n", $level, $message), FILE_APPEND | LOCK_EX);
});

$mf->post('/webhooks/payments', function ($request, $response, $database, $logger, $errors) use ($mf) {
    // the signature is the HMAC-SHA256 of the raw body, made with the shared secret
    $signature = 'sha256='.hash_hmac('sha256', $request->body(), $mf('webhook_secret'));

    if (!hash_equals($signature, (string) $request->header('X-Signature'))) {
        $logger->warning('Invalid signature from {ip}', ['ip' => $request->ip()]);
        $errors->abort(401, 'Invalid signature');
    }

    $event = $request->validate([
        'id' => ['required', 'string'],
        'type' => ['required', 'in' => ['payment.succeeded', 'payment.failed']],
        'data.order_id' => ['required', 'integer'],
        'data.amount' => ['required', 'integer', 'min' => 1],
    ]);

    // providers retry the deliveries: each event is processed once
    if (!is_null($database->read('events', $event['id']))) {
        $logger->info('Event {id} already processed', ['id' => $event['id']]);

        return $response->noContent();
    }

    $database->transactional(function () use ($database, $event) {
        $database->create('events', ['id' => $event['id'], 'type' => $event['type'], 'received_at' => time()]);

        // update the order, send an email, ...
    });

    $logger->info('Event {id} processed: {type} of order {order}', [
        'id' => $event['id'],
        'type' => $event['type'],
        'order' => $event['data']['order_id'],
    ]);

    return $response->noContent();
});
```

## Trying it

```bash
php -S localhost:8080 index.php
```

```bash
BODY='{"id":"evt_1","type":"payment.succeeded","data":{"order_id":42,"amount":1999}}'
SIGNATURE="sha256=$(printf '%s' "$BODY" | openssl dgst -sha256 -hmac 'change-me' | sed 's/^.* //')"

$ curl -i -X POST localhost:8080/webhooks/payments -H 'Content-Type: application/json' -H "X-Signature: $SIGNATURE" -d "$BODY"
HTTP/1.1 204 No Content

$ curl -X POST localhost:8080/webhooks/payments -H 'Content-Type: application/json' -H 'X-Signature: sha256=wrong' -d "$BODY"
{"error":{"status":401,"title":"Unauthorized","message":"Invalid signature"}}
```

The lines added to `webhooks.log`:

```
info: Event evt_1 processed: payment.succeeded of order 42
warning: Invalid signature from 127.0.0.1
```

## How it works

-   `$request->body()` is the raw body, exactly as signed by the provider: the signature is compared with [`hash_equals`](https://www.php.net/manual/en/function.hash-equals.php), which takes the same time for any value
-   `abort(401)` and the validation errors are JSON responses, thanks to the `json` option of the [[Errors]] handling
-   `data.order_id` validates a nested field of the JSON body (see [[Validation]])
-   the identifiers of the processed events are stored, so the deliveries sent again are ignored but answered with a success, otherwise the provider would keep retrying
-   the `webhook_secret` setting isn't injected in the callback like the services, so it's read with `$mf()` (see [[Dependency Injection]])

Each provider has its own signature format (header name, prefix, timestamps): check its documentation and change the first lines of the route.
