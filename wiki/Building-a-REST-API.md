This guide builds a small JSON API to manage books, using the [[Database]], [[Migrations]] and the HTTP features.

## Application

```php
<?php
// index.php

$mf = require_once 'crystal.php';

$db = $mf('database');
$db::setPdo(new PDO('sqlite:'.__DIR__.'/books.sqlite'));

$db->query('CREATE TABLE IF NOT EXISTS books (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    author TEXT NOT NULL,
    year INTEGER
)');

// see Errors, CORS, Request and Response
$mf->middleware($mf('cors')->middleware());
$mf->middleware($mf('response')->conditional());

$findBook = function ($id) use ($mf, $db) {
    $book = $db->read('books', $id);

    if (empty($book)) {
        $mf('errors')->abort(404, sprintf('Book %d not found.', $id));
    }

    return $book;
};

$rules = [
    'title' => 'required|string|max:255',
    'author' => 'required|string|max:255',
    'year' => 'nullable|integer|between:0,2100',
];

$mf->group('/api/books', function ($mf) use ($db, $findBook, $rules) {
    $mf->get('', function () use ($mf) {
        return $mf('paginator')->paginate('books', ['order' => 'id']);
    }, ['name' => 'listBooks']);

    $mf->get('/{id:\d+}', function ($id) use ($findBook) {
        return $findBook($id);
    }, ['name' => 'getBook']);

    $mf->post('', function () use ($mf, $db, $rules) {
        $data = $mf('request')->validate($rules);
        $db->create('books', $data);
        $id = $db->query('SELECT last_insert_rowid() AS id')[0]['id'];

        return $mf('response')->created('/api/books/'.$id, $db->read('books', $id));
    }, ['name' => 'createBook']);

    $mf->put('/{id:\d+}', function ($id) use ($mf, $db, $findBook, $rules) {
        $findBook($id);
        $db->update('books', $mf('request')->validate($rules), $id);

        return $findBook($id);
    }, ['name' => 'updateBook']);

    $mf->delete('/{id:\d+}', function ($id) use ($mf, $db, $findBook) {
        $findBook($id);
        $db->delete('books', $id);

        return $mf('response')->noContent();
    }, ['name' => 'deleteBook']);
}, [
    'middleware' => [$mf('ratelimit')->middleware(['limit' => 120])],
]);

return $mf;
```

Rate limiting stores counters in the [[Cache]], configure a driver before running the application.

## Usage

```bash
php -S localhost:8000 index.php

curl -X POST localhost:8000/api/books -H 'Content-Type: application/json' \
    -d '{"title":"Dune","author":"Frank Herbert","year":1965}'
# 201 Created, Location: /api/books/1

curl localhost:8000/api/books?per_page=10
curl -X DELETE localhost:8000/api/books/1   # 204 No Content
curl -X PATCH localhost:8000/api/books/1    # 405 Method Not Allowed
curl -X POST localhost:8000/api/books -H 'Content-Type: application/json' -d '{}'
# 422 Unprocessable Content with the validation errors
```

## Authentication

Protect write operations with a Bearer token, see [[Authentication]]:
```php
$auth = $mf('auth')->bearer(getenv('JWT_SECRET'));

$mf->post('', $createBook, ['middleware' => [$auth]]);
```

## Tests

```php
<?php
// tests/books.php

$mf = require __DIR__.'/../index.php';
$mf->testing();

$response = $mf->handle('POST', '/api/books', ['json' => ['title' => 'Dune', 'author' => 'Frank Herbert']]);
assert(201 === $response->getStatusCode());

$response = $mf->handle('GET', $response->getHeaderLine('Location'));
assert('Dune' === $response->json()['title']);
```

See [[Testing]] for more details.
