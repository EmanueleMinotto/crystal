The `logger` service writes messages with an interface similar to [PSR-3](https://www.php-fig.org/psr/psr-3/).
By default messages go to the system log with [`syslog`](https://www.php.net/manual/en/function.syslog.php), see below to write them to a file.

```php
$mf->post('/orders', function ($request, $logger) {
    // ...
    $logger->info('Order {id} created by {email}', ['id' => 42, 'email' => 'alice@example.com']);
    // "Order 42 created by alice@example.com"
});
```

## Levels

Each level has its method, from the most to the least severe:

```php
$logger = $mf('logger');

$logger->emergency('The database is down');
$logger->alert('The disk is almost full');
$logger->critical('The payment provider is unreachable');
$logger->error('The email to {email} failed', ['email' => 'alice@example.com']);
$logger->warning('Login failed for {email}', ['email' => 'alice@example.com']);
$logger->notice('The cache was cleared');
$logger->info('User {id} logged in', ['id' => 42]);
$logger->debug('Query executed in {ms} ms', ['ms' => 12]);

$logger->log('warning', 'Same as warning()');
```

`{key}` placeholders are replaced with the values of the context, as described by [PSR-3](https://www.php-fig.org/psr/psr-3/#12-message).
The context is logged too, so it can contain values that aren't in the message.

## Shared context

Values added to the shared context are added to every message:

```php
$logger = $mf('logger');

$logger->addContext('request_id', bin2hex(random_bytes(8)));

$logger->info('Page viewed'); // context: ['request_id' => '...']
```

```php
$logger->setContext(['app' => 'blog']);  // replace the shared context
$logger->unsetContext('app', 'user_id'); // remove some keys
$logger->resetContext();                 // remove every key
$logger->getContext();                   // the shared context
```

The context of a message takes precedence over the shared one.

## Writing to a file

`setImplementation()` replaces `syslog`, it receives the level, the message (with the placeholders replaced) and the context (shared and local):

```php
$mf('logger')->setImplementation(function ($level, $message, $context) {
    $line = sprintf("[%s] %s: %s %s\n", date('c'), $level, $message, json_encode($context));

    file_put_contents(__DIR__.'/logs/app.log', $line, FILE_APPEND | LOCK_EX);
});

// [2026-10-26T10:00:00+00:00] info: User 42 logged in {"id":42}
```

Only from a level up:

```php
$mf('logger')->setImplementation(function ($level, $message, $context) {
    if (in_array($level, ['debug', 'info', 'notice'], true)) {
        return;
    }

    error_log(strtoupper($level).': '.$message.' '.json_encode($context));
});
```

`resetImplementation()` uses `syslog` again, whose identity and options can be set with [`openlog`](https://www.php.net/manual/en/function.openlog.php):

```php
openlog('my-app', LOG_PID, LOG_USER);
```

## Errors

With the [[Errors]] handling enabled, exceptions with a 5xx status code are logged with the `error` level.
To use another logger, like Monolog, replace the service (see [[Dependency Injection]]):

```php
$mf('logger', $monolog);
```
