An API endpoint computing statistics with a slow query: the result is kept in the [[Cache]] for a minute, removed when the data changes, and clients that already have it receive a `304 Not Modified` response without body.

```
stats/
├── crystal.php
└── index.php
```

`index.php`:

```php
<?php

$mf = require __DIR__.'/crystal.php';

$mf('errors')->enable();

is_dir(__DIR__.'/cache') || mkdir(__DIR__.'/cache');
$mf('cache')::setDriver('filename', __DIR__.'/cache');

$mf('database')::setPdo(new PDO('sqlite:'.__DIR__.'/shop.sqlite'));
$mf('database')->query('CREATE TABLE IF NOT EXISTS orders (id INTEGER PRIMARY KEY, status TEXT NOT NULL, total INTEGER NOT NULL)');

$mf->get('/api/stats', function ($cache, $database, $response) {
    $stats = $cache->get('stats');

    if (is_null($stats)) {
        $stats = $database->query('SELECT status, COUNT(*) AS orders, SUM(total) AS total FROM orders GROUP BY status ORDER BY status');
        $cache->set('stats', $stats, 60);
    }

    // the ETag is the hash of the body: the same statistics, the same tag
    return $response->setEtag()->json(['stats' => $stats]);
});

$mf->post('/api/orders', function ($request, $response, $database, $cache) {
    $order = $request->validate([
        'status' => ['required', 'in' => ['paid', 'refunded']],
        'total' => ['required', 'integer', 'min' => 1],
    ]);

    $order['id'] = $database->create('orders', $order);

    // the statistics changed
    $cache->delete('stats');

    return $response->created($order, '/api/orders/'.$order['id']);
});
```

## Trying it

```bash
php -S localhost:8080 index.php
```

```bash
$ curl -X POST localhost:8080/api/orders -H 'Content-Type: application/json' -d '{"status":"paid","total":1999}'
{"status":"paid","total":1999,"id":1}

$ curl -i localhost:8080/api/stats
HTTP/1.1 200 OK
ETag: "4f7c4c5e1b3a..."

{"stats":[{"status":"paid","orders":"1","total":"1999"}]}
```

The browser (or any client) sends the tag of the response it already has, the response has no body:

```bash
$ curl -i localhost:8080/api/stats -H 'If-None-Match: "4f7c4c5e1b3a..."'
HTTP/1.1 304 Not Modified
```

After a new order the statistics change, so does the tag: the client receives the new ones.

## How it works

-   the query runs at most once a minute, the other requests read the result from the cache
-   `$cache->delete('stats')` removes the outdated result as soon as an order is created, so the statistics are never older than the data
-   `setEtag()` without arguments uses the hash of the body as entity tag: when the `If-None-Match` header of the request matches it, the response is sent as a 304 without body (see [[Request Response]])
-   the 304 saves bandwidth, not work: the statistics are still read, from the cache. To skip the work too, compute the tag from a cheaper value, like the time of the latest change

## Going further

-   use the `apcu`, `redis` or `memcached` drivers for faster caches, or a `chain` of them (see [[Cache]])
-   group the keys of a feature in a [namespace](Cache#namespaces), to clear them together
