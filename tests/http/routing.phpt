--TEST--
Routing helpers, placeholders and groups
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$mf->testing();

$mf->get('/users/{id:\d+}', function ($id) {
    return 'user '.$id;
});
$mf->get('/posts/{slug}/comments/{comment}', function ($comment, $slug) {
    return $slug.' '.$comment;
});
$mf->get('/years/(\d{4})', function ($year) {
    return 'year '.$year;
});
$mf->post('/users', function () {
    return 'created';
});
$mf->any('/ping', function () use ($mf) {
    return $mf('request')->method();
});

$mf->group('/api', function ($mf) {
    $mf->group('/v1', function ($mf) {
        $mf->put('/items/{id}', function () use ($mf) {
            return 'v1 item '.$mf('request')->param('id');
        });
    });
    $mf->delete('/items/{id}', function ($id) {
        return 'deleted '.$id;
    });
});

foreach (array(
    array('GET', '/users/42'),
    array('GET', '/users/abc'),
    array('GET', '/posts/hello/comments/7'),
    array('GET', '/years/2026'),
    array('POST', '/users'),
    array('PATCH', '/ping'),
    array('PUT', '/api/v1/items/3'),
    array('DELETE', '/api/items/9'),
    array('GET', '/users/42?foo=bar'),
) as $request) {
    $response = $mf->handle($request[0], $request[1]);
    echo rtrim($request[0].' '.$request[1].' => '.$response->getStatusCode().' '.($response->getStatusCode() < 400 ? $response->getBody() : '')).PHP_EOL;
}

?>
--EXPECT--
GET /users/42 => 200 user 42
GET /users/abc => 404
GET /posts/hello/comments/7 => 200 hello 7
GET /years/2026 => 200 year 2026
POST /users => 200 created
PATCH /ping => 200 PATCH
PUT /api/v1/items/3 => 200 v1 item 3
DELETE /api/items/9 => 200 deleted 9
GET /users/42?foo=bar => 200 user 42
