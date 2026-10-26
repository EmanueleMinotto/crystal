From the command line, `$mf()` receives a callback without a path: it's invoked with the [arguments](https://www.php.net/manual/en/reserved.variables.argv.php) of the script.

`hello.php`:

```php
<?php

$mf = require __DIR__.'/crystal.php';

$mf(function ($name = 'World') {
    echo 'Hello '.$name.'!'.PHP_EOL;
});
```

```bash
$ php hello.php
Hello World!

$ php hello.php Alice
Hello Alice!
```

Give a default value to the parameters, so the script works without arguments too.

## Arguments and exit codes

```php
// Usage: php greet.php <name> [<times>]
$mf(function ($name = null, $times = 1) {
    if (is_null($name)) {
        fwrite(STDERR, 'Usage: php greet.php <name> [<times>]'.PHP_EOL);
        exit(1);
    }

    for ($i = 0; $i < (int) $times; ++$i) {
        echo 'Hello '.$name.'!'.PHP_EOL;
    }
});
```

## Using the services

The services are available with `$mf()`, configured as in the website:

```php
// bin/cleanup.php: removes the sessions older than a week
$mf = require __DIR__.'/../crystal.php';

$mf('database')::setPdo(new PDO('sqlite:'.__DIR__.'/../app.sqlite'));

$mf(function () use ($mf) {
    $mf('database')->delete('sessions', ['updated_at < ?' => time() - 7 * 86400]);

    $mf('logger')->info('Old sessions removed');
});
```

Unlike the routes, the callbacks of the command line don't receive the services as parameters: use `use ($mf)`.
See [[Migrations]] for a migrations command.

## Priorities

The second argument is the priority: `0` (the default) runs first, then `1`, `2`, and so on.

```php
$mf(function () { echo 'B'; }, 1);
$mf(function () { echo 'A'; });

// Output: AB
```

## Command line or website

Crystal is in command line mode when there's no requested URL (`$_SERVER['REQUEST_URI']` is empty), so:

-   a script is either a website or a command line script: routes like `$mf->get()` can't be defined from the command line
-   a callback without a path in `index.php` (`$mf(function () { ... })`) doesn't work in the website

Share the configuration between them with a file required by both, like `bootstrap.php`.
