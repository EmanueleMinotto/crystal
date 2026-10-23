Crystal can integrate [PSR-7](https://www.php-fig.org/psr/psr-7/) HTTP messages and [PSR-15](https://www.php-fig.org/psr/psr-15/) request handlers and middlewares.

The support is opt-in: crystal doesn't bundle any interface or implementation, so it still has no external dependencies. Install the packages you need, for example:

```
composer require nyholm/psr7 psr/http-server-middleware
```

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

Messages are created using a [PSR-17](https://www.php-fig.org/psr/psr-17/) factory, `Nyholm\Psr7\Factory\Psr17Factory` and `GuzzleHttp\Psr7\HttpFactory` are detected automatically. Any other factory implementing the server request, URI, stream and uploaded file factory interfaces can be used:

```php
$mf('http')->setFactory($factory);
```

The request can be replaced too, for example in tests:

```php
$mf('http')->setRequest($request);
```
