Crystal provides a logger to help developer tracking informations.<br />
The logger is provided as an object with an interface similar to [PSR-3](https://www.php-fig.org/psr/psr-3/).

Both the implementations use [`syslog`](https://www.php.net/manual/en/function.syslog.php), so you can manage the implementation through [`openlog`](https://www.php.net/manual/en/function.openlog.php).

The logger allows also placeholders inside the message, like defined in [this section of PSR-3](https://www.php-fig.org/psr/psr-3/#12-message): for each key in the context, all the placeholders defined as `{key}` inside the message will be replaced with the related context value.

```php
$mf(function () use ($mf) {
    $logger = $mf('logger');

    $logger->info('lorem ipsum', [
        'foo' => true,
    ]);
    $logger->debug('dolor sit amet');
});
```

#### Shared Context

```php
$mf(function () use ($mf) {
    $logger = $mf('logger');

    $logger->addContext('session_id', session_id());

    // replace all the other keys in the shared context
    // $logger->setContext(['session_id' => session_id()]);

    // reset the shared context
    // $logger->resetContext();

    // to remove some keys from shared context
    // $logger->unsetContext('foo', 'bar');

    // if you need to know what's in the shared context
    // $context = $logger->getContext();

    // 'session_id' will be added implicitly to the context
    $logger->info('lorem ipsum', [
        'foo' => true,
    ]);
});
```

#### Custom Implementation

If you need to redefine the logger implementation (e.g. for a custom logging), you can redefine the logger implementation with the `setImplementation` method and it takes the arguments:

* `level: string`: which could be one of [these levels](https://www.php-fig.org/psr/psr-3/#5-psrlogloglevel)
* `message: string`: the logging message
* `context: array`: an optional array of context, this could be an empty array or could contain the union of shared and local context data

```php
$mf(function () use ($mf) {
    $logger = $mf('logger');

    $logger->addContext('bar', 'test');

    $logger->setImplementation(function ($level, $message, $context) {
        echo sprintf("%s: %s - %s\n", $level, $message, json_encode($context));
    });

    $logger->info('lorem ipsum', array(
        'foo' => true,
    ));
    $logger->debug('dolor {placeholder} amet', array(
        'bar' => 1,
        'placeholder' => 'sit',
    ));
    $logger->emergency('consectetur adipisci elit');
});
```

in this case output will be

```
info: lorem ipsum - {"bar":"test","foo":true}
debug: dolor sit amet - {"bar":1,"placeholder":"sit"}
emergency: consectetur adipisci elit - {"bar":"test"}
```
