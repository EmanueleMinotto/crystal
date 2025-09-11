Autoloader extends the [PSR-0](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md) standard.

It uses PHP [SPL extensions](http://it1.php.net/manual/en/function.spl-autoload-extensions.php) and `include_path` directories.

## Usage
Add a local directory
``` php
ini_set('include_path', ini_get('include_path').PATH_SEPARATOR.'vendor');
```

Get a class
```php
// get_include_path() == '.;/var/www/'
// spl_autoload_extensions() == '.php,.class.php'

require_once 'crystal.php';

// The autoloader looks for:
// 1. ./Bar/Foo.php
// 2. ./Bar/Foo.class.php
// 3. /var/www/Bar/Foo.php
// 4. /var/www/Bar/Foo.class.php
$Foo = new Bar\Foo;
```