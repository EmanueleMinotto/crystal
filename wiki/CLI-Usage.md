Hello World
```php
$mf = require_once('crystal.php');

// Usage: $ php test.php

$mf(function () {
    echo 'Hello World!';
});
```
Like CGI usage, first argument must be [callable](http://php.net/manual/en/language.types.callable.php).
Function arguments are `$argv` [arguments](http://php.net/manual/en/reserved.variables.argv.php)
```php
// Usage: $ php test.php foo bar

$mf(function ($a, $b) {
    echo $b . ' ' . $a;
});

// Output: bar foo
```