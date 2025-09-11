It's suggested to create the unused PHP [`main`](http://it2.php.net/manual/en/function.main.php) function.

```php
if (!function_exists('main')) {
    function main() {
        static $mf;

        if (is_null($mf)) {
            $mf = require_once 'crystal.php';
        }

        return call_user_func_array($mf, func_get_args());
    }
}

main('/', function () {
    echo 'Hello World!';
});
```