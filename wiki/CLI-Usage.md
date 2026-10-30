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

By default the callbacks of the command line don't receive the services as parameters: use `use ($mf)`, or enable the `cli` service (see below).
See [[Migrations]] for a migrations command.

## Options and services

Enable the `cli` service before defining the callbacks: options (like `--dry-run` or `--limit=10`) are removed from the arguments and read with `option()`, and the parameters without an argument receive the services, like in the routes.

`bin/report.php`:

```php
<?php

$mf = require __DIR__.'/../crystal.php';

$mf('cli')->enable();

// Usage: php bin/report.php [<title>] [--limit=<n>] [--verbose]
$mf(function ($title = 'Report', $cli = null, $validator = null) {
    $errors = $validator->check($cli->options(), ['limit' => ['integer', 'min' => 1]]);

    if (!empty($errors)) {
        fwrite(STDERR, $errors['limit'][0].PHP_EOL);
        exit(1);
    }

    echo $title.': '.$cli->option('limit', 3).' rows'.PHP_EOL;

    if ($cli->option('verbose')) {
        echo 'Options: '.json_encode($cli->options()).PHP_EOL;
    }
});
```

```bash
$ php bin/report.php
Report: 3 rows

$ php bin/report.php Sales --limit=10 --verbose
Sales: 10 rows
Options: {"limit":"10","verbose":true}
```

-   `--name` is `true`, `--name=value` is the string `value`, a repeated option keeps the last value
-   arguments after `--`, and the ones starting with a single dash (like `-5`), aren't options
-   `$cli->arguments()` returns the arguments without the options
-   the arguments come first: a parameter receives a service only when the script has no argument for it, so put the services after the arguments
-   services after an optional argument need a default value too (like `$cli = null`): the service is injected anyway
-   parameters typed with a class receive an instance made by the [[Dependency Injection]] container
-   a required parameter without an argument nor a service throws an `ArgumentCountError` naming it, give the arguments a default value to print the usage instead

Callbacks defined before `enable()` receive the arguments as they are, so the scripts not enabling it work as before.

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
