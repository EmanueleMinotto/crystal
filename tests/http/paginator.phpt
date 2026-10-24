--TEST--
Paginated responses from database tables and arrays
--SKIPIF--
<?php if (!extension_loaded('pdo_sqlite')) echo 'skip pdo_sqlite not available'; ?>
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$mf->testing();

$db = $mf('database');
$db::setPdo(new PDO('sqlite::memory:'));
$db->query('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, active INTEGER NOT NULL)');

for ($i = 1; $i <= 7; $i++) {
    $db->create('users', array('id' => $i, 'name' => 'user'.$i, 'active' => $i % 2));
}

$mf->get('/users', function () use ($mf) {
    return $mf('paginator')->paginate('users', array(
        'fields' => array('id', 'name'),
        'where' => array('active' => 1),
        'order' => 'id DESC',
        'per_page' => 2,
    ));
});

$mf->get('/colors', function () use ($mf) {
    return $mf('paginator')->paginateArray(array('red', 'green', 'blue'), array('max_per_page' => 2));
});

$response = $mf->handle('GET', '/users?page=2&sort=name');
echo $response->getHeaderLine('X-Total-Count').PHP_EOL;
echo $response->getHeaderLine('Link').PHP_EOL;
echo json_encode($response->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

echo $mf->handle('GET', '/colors?per_page=2&page=2')->getBody().PHP_EOL;
echo $mf->handle('GET', '/colors?per_page=3')->getStatusCode().PHP_EOL;
echo $mf->handle('GET', '/colors?page=0')->getStatusCode().PHP_EOL;

?>
--EXPECT--
4
</users?page=1&sort=name&per_page=2>; rel="first", </users?page=1&sort=name&per_page=2>; rel="prev", </users?page=2&sort=name&per_page=2>; rel="last"
{
    "data": [
        {
            "id": "3",
            "name": "user3"
        },
        {
            "id": "1",
            "name": "user1"
        }
    ],
    "meta": {
        "page": 2,
        "per_page": 2,
        "total": 4,
        "last_page": 2
    },
    "links": {
        "first": "/users?page=1&sort=name&per_page=2",
        "prev": "/users?page=1&sort=name&per_page=2",
        "next": null,
        "last": "/users?page=2&sort=name&per_page=2"
    }
}
{"data":["blue"],"meta":{"page":2,"per_page":2,"total":3,"last_page":2},"links":{"first":"/colors?per_page=2&page=1","prev":"/colors?per_page=2&page=1","next":null,"last":"/colors?per_page=2&page=2"}}
400
400
