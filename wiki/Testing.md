In testing mode routes aren't dispatched when the script ends, requests are dispatched in-process using `handle()` that returns the response without sending it.

```php
$mf = require 'crystal.php';
$mf->testing();

require 'routes.php';

$response = $mf->handle('POST', '/users?notify=1', [
    'json' => ['email' => 'alice@example.com'], // or 'body', 'form'
    'headers' => ['Authorization' => 'Bearer '.$token],
    'server' => ['REMOTE_ADDR' => '127.0.0.1'],
]);

assert(201 === $response->getStatusCode());
assert('/users/1' === $response->getHeaderLine('Location'));
assert('alice@example.com' === $response->json()['email']);
```

Global middlewares, route middlewares, errors and automatic 404/405 responses are handled as in a real request.

In command line mode `header()` and `http_response_code()` have no effect, so in testing mode set status codes and headers returning [[response objects|Request and Response]] from callbacks.

## Routes in command line mode

Routes can be defined in command line mode too, so the same application file can be loaded by tests and scripts, they're dispatched only calling `handle()`.
