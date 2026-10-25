The `paginator` service splits the rows of a table in pages, using the [[Database]] service.
The current page is read from the `page` value of the query string (`/api/posts?page=2`).

```php
$mf('/api/posts', function () use ($mf) {
    $paginator = $mf('paginator');

    // published posts, 20 for each page, the newest first
    $page = $paginator->paginate('posts', ['status' => 'published'], 'id DESC', 20);

    return $paginator->response($page);
});
```

## Results

`paginate($table, $where, $order, $perPage = 15, $fields = '*')` returns the rows of the current page, the counts and the URLs of the other pages.
`$where`, `$order` and `$fields` are the same of `select()` (see [[Database]]).

```json
{
    "data": [{"id": "41", "status": "published"}, ...],
    "meta": {"page": 2, "per_page": 20, "total": 47, "pages": 3},
    "links": {
        "first": "/api/posts?page=1",
        "prev": "/api/posts?page=1",
        "next": "/api/posts?page=3",
        "last": "/api/posts?page=3"
    }
}
```

-   `prev` is `null` on the first page and `next` on the last one
-   the other values of the query string (like filters) are kept in the URLs
-   missing or invalid pages (`0`, `-1`, `abc`) are the first page, pages after the last one have no rows
-   two queries are executed: `COUNT(*)` and `SELECT` with `LIMIT` and `OFFSET`

Use an order on a unique column (like `id DESC` or `created_at DESC, id DESC`), otherwise the database can return the same row in more pages.

`response($page)` returns a JSON [[response|Request Response]] with the [`Link` header](https://www.rfc-editor.org/rfc/rfc8288), used by many API clients to follow the pages:

```
Link: </api/posts?page=1>; rel="first", </api/posts?page=1>; rel="prev", </api/posts?page=3>; rel="next", </api/posts?page=3>; rel="last"
```

With [[CORS]], add `Link` to the `expose` option so JavaScript clients can read it.

## Other formats

The result is an array, so it can be used anywhere, i.e. in a [[Template Engine]] page:

```php
$page = $mf('paginator')->paginate('posts', null, 'id DESC', 10);

echo $mf('template')->render('posts.html.php', ['posts' => $page['data'], 'links' => $page['links']]);
```

`page()` returns the current page and `url($page)` the URL of a page.
