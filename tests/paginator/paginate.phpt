--TEST--
Pagination of database rows
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/posts?page=2&status=published';
$_SERVER['SCRIPT_NAME'] = '/';
$_GET = ['page' => '2', 'status' => 'published'];

$db = $mf('database');
$db::setPdo(new PDO('sqlite::memory:'));
$db->query('CREATE TABLE posts (id INTEGER PRIMARY KEY, status TEXT NOT NULL)');

for ($i = 1; $i <= 7; $i++) {
    $db->create('posts', ['status' => 4 === $i ? 'draft' : 'published']);
}

$paginator = $mf('paginator');

// 6 published posts, 4 for each page
$result = $paginator->paginate('posts', ['status' => 'published'], 'id', 4, ['id']);
echo json_encode($result, JSON_UNESCAPED_SLASHES) . "\n";

$response = $paginator->response($result);
var_dump($response->header('Content-Type'), $response->header('Link'));

// first page, invalid pages are the first one
foreach (['1', '0', '-3', 'abc', '1.5'] as $page) {
    $_GET['page'] = $page;
    echo $page . ': ' . json_encode($paginator->paginate('posts', null, 'id DESC', 3, 'id')['data']) . "\n";
}

$_GET['page'] = ['x'];
var_dump($paginator->page());

// pages after the last one are empty
$_GET['page'] = '9';
$result = $paginator->paginate('posts', null, 'id', 5);
echo json_encode([$result['data'], $result['meta'], $result['links']['prev'], $result['links']['next']], JSON_UNESCAPED_SLASHES) . "\n";

// no rows
$result = $paginator->paginate('posts', ['status' => 'deleted']);
echo json_encode($result['meta']) . "\n";

try {
    $paginator->paginate('posts', null, null, 0);
} catch (InvalidArgumentException $exception) {
    echo $exception->getMessage() . "\n";
}

?>
--EXPECT--
{"data":[{"id":"6"},{"id":"7"}],"meta":{"page":2,"per_page":4,"total":6,"pages":2},"links":{"first":"/api/posts?page=1&status=published","prev":"/api/posts?page=1&status=published","next":null,"last":"/api/posts?page=2&status=published"}}
string(16) "application/json"
string(149) "</api/posts?page=1&status=published>; rel="first", </api/posts?page=1&status=published>; rel="prev", </api/posts?page=2&status=published>; rel="last""
1: [{"id":"7"},{"id":"6"},{"id":"5"}]
0: [{"id":"7"},{"id":"6"},{"id":"5"}]
-3: [{"id":"7"},{"id":"6"},{"id":"5"}]
abc: [{"id":"7"},{"id":"6"},{"id":"5"}]
1.5: [{"id":"7"},{"id":"6"},{"id":"5"}]
int(1)
[[],{"page":9,"per_page":5,"total":7,"pages":2},"/api/posts?page=2&status=published",null]
{"page":9,"per_page":15,"total":0,"pages":1}
Rows of each page must be at least 1.
