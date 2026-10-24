The `config` service stores the application configuration and loads environment variables from `.env` files.

```php
$config = $mf('config');
```

## Configuration

Configuration can be loaded from arrays or files: `.php` (returning an array), `.json` and `.ini` (sections become nested arrays, values are typed).
Every call is merged recursively into the values already loaded: associative arrays are merged key by key, while lists and scalars are replaced.

```php
// config/app.php
return [
    'app' => ['name' => 'crystal', 'debug' => false],
    'database' => ['dsn' => getenv('DATABASE_DSN')],
];
```

```php
$config->load(__DIR__.'/config/app.php');
$config->load(__DIR__.'/config/local.json'); // overrides only the keys it defines
$config->load(['app' => ['debug' => true]]);
```

Values are accessed using the dot notation:

```php
$config->get('app.name');                  // "crystal"
$config->get('database.port', 3306);       // 3306, the default value
$config->has('app.debug');                 // true
$config->set('cache.ttl', 3600);           // intermediate levels are created
$config->all();                            // every value as an array
```

## Environment variables

`loadEnv()` reads a `.env` file and exposes the variables through `getenv()`, `$_ENV` and `$_SERVER`.
Variables already defined in the environment (e.g. by the webserver) are **not** replaced, unless the second argument is `true`.

```
# .env
APP_ENV=dev
APP_DEBUG=true
export DATABASE_DSN="sqlite:${APP_ENV}.sqlite"
GREETING='Hello ${NAME}'   # single quotes: literal value
MOTD="first line
second line\tand a tab"
```

```php
$config->loadEnv(__DIR__.'/.env');        // keep variables already defined
$config->loadEnv(__DIR__.'/.env', true);  // replace them
```

Supported syntax:

-   blank lines and lines starting with `#` are ignored
-   an optional `export ` prefix
-   inline comments in unquoted values, when preceded by a space (`A=b#c` is `b#c`)
-   single quoted values are taken literally
-   double quoted values support `\n`, `\r`, `\t`, `\"`, `\\`, `\$` and can span multiple lines
-   `${NAME}` references are replaced in unquoted and double quoted values (undefined variables become an empty string)

`env()` reads a variable converting `true`, `false`, `null` and `empty` (also wrapped in parentheses, e.g. `(true)`):

```php
$config->env('APP_DEBUG');            // true
$config->env('APP_SECRET', 'secret'); // "secret" when not defined
```

## Using both

Load the environment first, then the configuration files that rely on it:

```php
$config = $mf('config');

$config->loadEnv(__DIR__.'/.env');
$config->load(__DIR__.'/config/app.php');

$mf('/', function () use ($config) {
    echo $config->get('app.name');
});
```
