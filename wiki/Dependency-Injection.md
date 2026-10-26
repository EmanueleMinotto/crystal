Services are the objects of the application, like the database connection or a repository.
They're defined with a name and a factory, and created when they're used:

```php
class PostRepository
{
    private $database;

    public function __construct($database) { $this->database = $database; }

    public function latest() { return $this->database->select('posts', '*', [], 'id DESC', 10); }
}

$mf('posts', function () use ($mf) {
    return new PostRepository($mf('database'));
});

// the route receives the service by the name of the parameter
$mf->get('/api/posts', function ($posts) {
    return $posts->latest();
});

// or anywhere with $mf()
$latest = $mf('posts')->latest();
```

The name must be a valid PHP variable name, like `posts` or `mailer_queue`.

## Shared instances

A factory is invoked each time the service is requested, so it returns a new object each time.
To share one instance, define the service with `share()`: the factory is invoked on first use, then every call returns the same object.

```php
$mf('container')->share('posts', function () use ($mf) {
    return new PostRepository($mf('database'));
});

$mf('posts') === $mf('posts'); // true
```

An object created now can be passed directly:

```php
$mf('clock', new DateTimeImmutable());
```

## Settings

Values that aren't callable are settings: they're returned as they are.

```php
$mf('site_name', 'My blog');
$mf('per_page', 20);

$mf->get('/', function () use ($mf) {
    echo $mf('site_name'); // "My blog"
});
```

Settings aren't injected in the route callbacks (only objects are), use `use ($mf)` or the [[Configuration]] service for them.
Built-in settings, like `json_flags` (see [[Request Response]]), are described in the pages of their services.

## Factories with arguments

The `container` service ([PSR-11](https://www.php-fig.org/psr/psr-11/)-like) passes additional arguments to the factories:

```php
$mf('greeter', function ($greeting = 'Hello') {
    return function ($name) use ($greeting) {
        return $greeting.' '.$name.'!';
    };
});

$container = $mf('container');

$container->has('greeter');                   // true
$container->has('missing');                   // false
$container->get('greeter');                   // same as $mf('greeter')
call_user_func($container->get('greeter', 'Ciao'), 'Alice'); // "Ciao Alice!"
```

The container has the same values defined with `$mf()`, and `$container->set($name, $value)` is the same as `$mf($name, $value)`.

## Autowiring

`make()` creates an object of a class, creating the objects required by its constructor too:

```php
class Mailer {}

class Newsletter
{
    private $mailer;

    public function __construct(Mailer $mailer) { $this->mailer = $mailer; }
}

$newsletter = $mf('container')->make(Newsletter::class);
```

Objects made by `make()` are stored in the container with the name of their class, so the next calls return the same instances.

Route parameters typed with a class receive the object made by `make()`, without defining any service:

```php
$mf->post('/newsletter', function (Newsletter $newsletter) {
    // ...
});
```

### Interfaces

Constructors requiring interfaces need an alias to the class to use:

```php
interface CacheInterface {}

class FileCache implements CacheInterface {}

class Report
{
    public function __construct(CacheInterface $cache) { /* ... */ }
}

$mf('container')->alias(CacheInterface::class, FileCache::class);

$report = $mf('container')->make(Report::class); // receives a FileCache
```

## Built-in services

| Service | Description |
| --- | --- |
| `auth` | users, login, gates and API tokens, see [[Authentication]] |
| `cache` | cache with many drivers, see [[Cache]] |
| `config` | configuration and `.env` files, see [[Configuration]] |
| `container` | the container described above |
| `cors` | CORS headers, see [[CORS]] |
| `csrf` | CSRF tokens, see [[CSRF]] |
| `database` | PDO helpers, see [[Database]] |
| `errors` | error pages, see [[Errors]] |
| `limiter` | rate limiting, see [[Rate Limiting]] |
| `listener` | events, see [[Event Listener]] |
| `logger` | logs, see [[Logger]] |
| `mailer` | emails, see [[Mailer]] |
| `middleware` | middlewares, see [[Middleware]] |
| `migrations` | database migrations, see [[Migrations]] |
| `paginator` | pages of database rows, see [[Pagination]] |
| `request` | the current request, see [[Request Response]] |
| `response` | a **new** response each time, see [[Request Response]] |
| `router` | named routes and groups, see [[Routing]] |
| `session` | session values, see [[Session]] |
| `template` | PHP templates, see [[Template Engine]] |
| `validator` | validation rules, see [[Validation]] |

Except `response`, they're created once: every call returns the same instance.
They can be replaced like any other service:

```php
$mf('logger', new MyLogger());

$mf('logger'); // MyLogger instance, used by the other services too
```

## Details

-   strings that are names of functions are callable too: `$mf('format', 'date')` defines a factory invoking `date()`, use an array or an object to store them as settings
-   factories with required arguments aren't injected in the route callbacks, since they can't be invoked without arguments

The dependency injection exists because Faryshta Mextly pointed out that crystal, at the beginning, was only a routing system: every other service is now built on top of it.
