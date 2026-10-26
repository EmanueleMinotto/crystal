--TEST--
Wiki: the cache and the entity tags of the Cookbook
--SKIPIF--
<?php if (!extension_loaded('pdo_sqlite')) die('skip pdo_sqlite is required'); ?>
--FILE--
<?php

require __DIR__.'/helpers.php';

$app = docs_app('Cookbook-Caching');
$json = ['Content-Type' => 'application/json'];

$show = function (array $result) {
    echo rtrim($result['status'].' '.$result['body'])."\n";
};

$show(docs_request($app, 'POST', '/api/orders', ['headers' => $json, 'body' => '{"status":"paid","total":1999}']));

$stats = docs_request($app, 'GET', '/api/stats');
$show($stats);
$etag = '"'.sha1($stats['body']).'"';

// the client already has the statistics
$show(docs_request($app, 'GET', '/api/stats', ['headers' => ['If-None-Match' => $etag]]));

// a new order removes the cached statistics, so the tag changes
$show(docs_request($app, 'POST', '/api/orders', ['headers' => $json, 'body' => '{"status":"refunded","total":500}']));
$show(docs_request($app, 'GET', '/api/stats', ['headers' => ['If-None-Match' => $etag]]));

// cached: rows added without the API aren't visible yet
$pdo = new PDO('sqlite:'.$app.'/shop.sqlite');
$pdo->exec("INSERT INTO orders (status, total) VALUES ('paid', 1)");
$show(docs_request($app, 'GET', '/api/stats'));

?>
--EXPECT--
201 {"status":"paid","total":1999,"id":1}
200 {"stats":[{"status":"paid","orders":"1","total":"1999"}]}
304
201 {"status":"refunded","total":500,"id":2}
200 {"stats":[{"status":"paid","orders":"1","total":"1999"},{"status":"refunded","orders":"1","total":"500"}]}
200 {"stats":[{"status":"paid","orders":"1","total":"1999"},{"status":"refunded","orders":"1","total":"500"}]}
