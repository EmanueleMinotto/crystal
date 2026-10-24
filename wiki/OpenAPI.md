An [OpenAPI 3](https://spec.openapis.org/oas/v3.0.3) document can be generated from the defined routes.

```php
$mf->group('/api', function ($mf) {
    $mf->get('/users/{id:\d+}', $getUser, [
        'name' => 'getUser', // operationId
        'openapi' => [
            'summary' => 'Get a user',
            'responses' => ['200' => ['description' => 'The user'], '404' => ['description' => 'Not found']],
        ],
    ]);
}, ['openapi' => ['tags' => ['users']]]);

$mf->get('/openapi.json', function () use ($mf) {
    return $mf->openapi(['title' => 'Users API', 'version' => '1.0.0'], [
        'servers' => [['url' => 'https://api.example.com']],
    ]);
});
```

-   placeholders and named subpatterns become path parameters, `\d+` patterns are typed as `integer`
-   the `openapi` option is merged into the operation object, group options are merged too
-   routes using regular expressions that can't be represented as OpenAPI paths are skipped
-   `HEAD` methods are omitted

The document can be printed from the command line too:
```bash
php index.php crystal:openapi > openapi.json
```

## Routes list

```php
$mf->routes();
// [['methods' => ['GET', 'HEAD'], 'path' => '/api/users/{id:\d+}', 'name' => 'getUser', 'priority' => 0]]
```

```bash
php index.php crystal:routes
```
