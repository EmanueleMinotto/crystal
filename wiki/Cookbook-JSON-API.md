A JSON API to list, create, read, update and delete books, with validation, pagination, CORS and JSON errors.
It uses SQLite, so it runs anywhere PHP has the `pdo_sqlite` extension.

```
books-api/
├── crystal.php
└── index.php
```

`index.php`:

```php
<?php

$mf = require __DIR__.'/crystal.php';

// errors as JSON for every path, following RFC 9457 (Problem Details)
$mf('errors')->enable(false, ['json' => '.*', 'problem' => true]);
$mf('json_flags', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// JavaScript running on other websites can call the API
$mf('cors')->enable(['origins' => ['*'], 'expose' => ['Link', 'Location']]);

$mf('database')::setPdo(new PDO('sqlite:'.__DIR__.'/books.sqlite'));
$mf('database')->query('CREATE TABLE IF NOT EXISTS books (id INTEGER PRIMARY KEY, title TEXT NOT NULL, author TEXT NOT NULL, year INTEGER)');

// the book with the given id, or a 404 response
$mf('books', function () use ($mf) {
    return function ($id) use ($mf) {
        $book = $mf('database')->read('books', $id);

        if (is_null($book)) {
            $mf('errors')->abort(404, 'Book not found');
        }

        return $book;
    };
});

$mf->get('/books', function ($request, $paginator) {
    $where = $request->query('author') ? ['author' => $request->query('author')] : [];

    return $paginator->response($paginator->paginate('books', $where, 'id DESC', 10));
});

$mf->get('/books/{id:[0-9]+}', function (int $id, $books) {
    return $books($id);
});

$mf->post('/books', function ($request, $response, $database) {
    $book = $request->validate([
        'title' => ['required', 'string', 'max' => 200],
        'author' => ['required', 'string', 'max' => 100],
        'year' => ['integer', 'between' => [1450, 2100]],
    ]);

    $book['id'] = $database->create('books', $book);

    return $response->created($book, '/books/'.$book['id']);
});

$mf->patch('/books/{id:[0-9]+}', function (int $id, $request, $database, $books) {
    $books($id);

    // only the fields sent are changed
    $changes = $request->validate([
        'title' => ['string', 'max' => 200],
        'author' => ['string', 'max' => 100],
        'year' => ['integer', 'between' => [1450, 2100]],
    ]);

    if (!empty($changes)) {
        $database->update('books', $changes, $id);
    }

    return $books($id);
});

$mf->delete('/books/{id:[0-9]+}', function (int $id, $response, $database, $books) {
    $books($id);
    $database->delete('books', $id);

    return $response->noContent();
});
```

The `books` service is a function shared by the routes, received as the `$books` parameter (see [[Dependency Injection]]).

## Trying it

```bash
php -S localhost:8080 index.php
```

Create a book, the response is 201 with the `Location` header:

```bash
$ curl -X POST localhost:8080/books -H 'Content-Type: application/json' \
    -d '{"title":"Il nome della rosa","author":"Umberto Eco","year":1980}'
{"title":"Il nome della rosa","author":"Umberto Eco","year":1980,"id":1}
```

Invalid data is a 422 response with the errors of each field:

```bash
$ curl -X POST localhost:8080/books -H 'Content-Type: application/json' -d '{"year":"soon"}'
{"type":"about:blank","title":"Unprocessable Content","status":422,"detail":"The given data is invalid.","errors":{"title":["The title field is required."],"author":["The author field is required."],"year":["The year field must be an integer.","The year field must be between 1450 and 2100."]}}
```

Read a book (values are read from the database as strings), or receive a 404:

```bash
$ curl localhost:8080/books/1
{"id":"1","title":"Il nome della rosa","author":"Umberto Eco","year":"1980"}

$ curl localhost:8080/books/99
{"type":"about:blank","title":"Not Found","status":404,"detail":"Book not found"}
```

Change some fields:

```bash
$ curl -X PATCH localhost:8080/books/1 -H 'Content-Type: application/json' -d '{"year":1981}'
{"id":"1","title":"Il nome della rosa","author":"Umberto Eco","year":"1981"}
```

List the books, 10 for each page, with the `Link` header (see [[Pagination]]):

```bash
$ curl 'localhost:8080/books?author=Umberto%20Eco'
{"data":[{"id":"1","title":"Il nome della rosa","author":"Umberto Eco","year":"1981"}],"meta":{"page":1,"per_page":10,"total":1,"pages":1},"links":{"first":"/books?author=Umberto%20Eco&page=1","prev":null,"next":null,"last":"/books?author=Umberto%20Eco&page=1"}}
```

Delete it, the response is 204 without body:

```bash
$ curl -X DELETE localhost:8080/books/1
```

## Going further

-   protect the routes changing the data with [API tokens](Authentication#api-tokens)
-   limit the requests of each client with the [[Rate Limiting]]
-   version the schema with [[Migrations]] instead of `CREATE TABLE IF NOT EXISTS`
