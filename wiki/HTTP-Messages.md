Crystal can integrate [PSR-7](https://www.php-fig.org/psr/psr-7/) HTTP messages and [PSR-15](https://www.php-fig.org/psr/psr-15/) request handlers and middlewares.

No package is required: crystal includes a default implementation of the PSR-7 messages and, when `psr/http-message` and `psr/http-server-*` aren't installed, it declares their interfaces.
Interfaces are declared only when requested (e.g. type hinted or implemented) and after any other autoloader, so installed packages always win.

Routes not using PSR-7 work exactly as before.

## Request

When a route callback has a parameter type hinted with `ServerRequestInterface` (or a parent interface), the current request is injected, created from globals the first time:

```php
use Psr\Http\Message\ServerRequestInterface;

$mf('/users/(?P<id>\d+)', function (ServerRequestInterface $request, $id) {
    echo $request->getAttribute('id') === $id; // named subpatterns are request attributes
});
```

Named subpatterns are injected by name, other subpatterns by position, as usual.

## Response

A `ResponseInterface` returned by a route callback is emitted: status, headers and body. When headers have already been sent (e.g. multiple actions on the same route), only the body is sent.

```php
$mf('/', function (ServerRequestInterface $request) use ($mf) {
    $factory = $mf('http')->getFactory();

    return $factory->createResponse(200)
        ->withHeader('Content-Type', 'text/plain')
        ->withBody($factory->createStream('Hello World!'));
});
```

## Request Handlers and Middlewares

A `RequestHandlerInterface` can be used as a route callback:

```php
$mf('/api', new ApiHandler(), 'GET|POST');
```

Middlewares are piped in a request handler with `pipe`, the last argument is the handler that creates the response.
Middlewares can be `MiddlewareInterface` instances or callables with the same signature, handlers can be `RequestHandlerInterface` instances or callables receiving the request:

```php
$mf('/admin', $mf('http')->pipe(
    new AuthenticationMiddleware(),
    function (ServerRequestInterface $request, RequestHandlerInterface $handler) {
        return $handler->handle($request)->withHeader('X-Frame-Options', 'DENY');
    },
    new AdminHandler()
));
```

## Configuration

Messages are created using a [PSR-17](https://www.php-fig.org/psr/psr-17/) factory:

1. the one defined with `setFactory()`
2. `Nyholm\Psr7\Factory\Psr17Factory` or `GuzzleHttp\Psr7\HttpFactory`, when installed
3. the default implementation, based on anonymous classes

The default implementation follows `psr/http-message` 1.x signatures, so it isn't available when `psr/http-message` 2.x is installed: in that case install an implementation too (e.g. `composer require nyholm/psr7`).
When using Composer, include its autoloader before `crystal.php`.

Any other factory implementing the server request, URI, stream and uploaded file factory interfaces can be used:

```php
$mf('http')->setFactory($factory);
```

The request can be replaced too, for example in tests:

```php
$mf('http')->setRequest($request);
```
