Crystal loads classes automatically following the [PSR-0](https://www.php-fig.org/psr/psr-0/) standard: the namespace and the underscores of the class name are directories.
Classes are searched in the directories of the [`include_path`](https://www.php.net/manual/en/ini.core.php#ini.include-path), with the extensions of [`spl_autoload_extensions()`](https://www.php.net/manual/en/function.spl-autoload-extensions.php) (`.inc` and `.php` by default).

## Usage

Add the directory of your classes to the `include_path`:

```
my-app/
├── crystal.php
├── index.php
└── src/
    └── App/
        ├── PostRepository.php    # App\PostRepository
        └── Mail/
            └── Welcome.php       # App\Mail\Welcome, or App_Mail_Welcome
```

```php
<?php

set_include_path(__DIR__.'/src'.PATH_SEPARATOR.get_include_path());

$mf = require __DIR__.'/crystal.php';

$posts = new App\PostRepository($mf('database')); // loads src/App/PostRepository.php
```

Route parameters typed with these classes receive an instance too (see [[Dependency Injection]]):

```php
$mf->get('/api/posts', function (App\PostRepository $posts) {
    return $posts->latest();
});
```

## How files are found

```php
// get_include_path() == '.:/var/www/lib'
// spl_autoload_extensions() == '.php,.class.php'

// new Bar\Foo looks for:
// 1. ./Bar/Foo.php
// 2. ./Bar/Foo.class.php
// 3. /var/www/lib/Bar/Foo.php
// 4. /var/www/lib/Bar/Foo.class.php
$foo = new Bar\Foo();
```

-   `_` in the class name is a directory separator (`Bar_Foo` is `Bar/Foo.php`), in the namespace it isn't
-   directories of the `include_path` that don't exist are skipped
-   it works together with other autoloaders, like the one of Composer
