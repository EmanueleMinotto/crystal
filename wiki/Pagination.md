The `paginator` service creates paginated JSON responses, page and size are read from the `page` and `per_page` query string parameters.

```php
$mf->get('/users', function () use ($mf) {
    return $mf('paginator')->paginate('users', [
        'fields' => ['id', 'name'],     // passed to the Database select()
        'where' => ['active' => 1],
        'order' => 'id DESC',
        'per_page' => 20,               // default page size, default 20
        'max_per_page' => 100,          // maximum requested size, default 100
    ]);
});
```

`GET /users?page=2&per_page=2`
```json
{
    "data": [{"id": "3", "name": "user3"}, {"id": "1", "name": "user1"}],
    "meta": {"page": 2, "per_page": 2, "total": 4, "last_page": 2},
    "links": {
        "first": "/users?page=1&per_page=2",
        "prev": "/users?page=1&per_page=2",
        "next": null,
        "last": "/users?page=2&per_page=2"
    }
}
```

Responses include the `X-Total-Count` header and the [`Link`](https://www.rfc-editor.org/rfc/rfc8288) header with the `first`, `prev`, `next` and `last` relations. Invalid `page` or `per_page` values result in a `400 Bad Request` [[error|Errors]].

Arrays can be paginated too:
```php
return $mf('paginator')->paginateArray($items, ['per_page' => 10]);
```
