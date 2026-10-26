The `database` service runs queries and CRUD operations over a [PDO](https://www.php.net/manual/en/book.pdo.php) connection.
Set the connection once, at the beginning of `index.php`:

```php
$mf = require __DIR__.'/crystal.php';

// SQLite
$mf('database')::setPdo(new PDO('sqlite:'.__DIR__.'/app.sqlite'));

// MySQL or MariaDB
$mf('database')::setPdo(new PDO('mysql:host=localhost;dbname=app;charset=utf8mb4', 'user', 'password'));

// PostgreSQL
$mf('database')::setPdo(new PDO('pgsql:host=localhost;dbname=app', 'user', 'password'));
```

Then the routes receive the service as the `$database` parameter:

```php
$mf->get('/api/posts/{id:[0-9]+}', function (int $id, $database) {
    return $database->read('posts', $id);
});
```

Errors are thrown as `PDOException`, and values are read as strings (`"42"`).

## Create, read, update and delete

```php
$database = $mf('database');

$id = $database->create('posts', ['title' => 'Hello', 'status' => 'draft']); // 1, the new id

$database->read('posts', $id);
// ['id' => '1', 'title' => 'Hello', 'status' => 'draft'], or null when it doesn't exist

$database->update('posts', ['status' => 'published'], $id);

$database->delete('posts', $id);
```

`read`, `update` and `delete` find the rows by `id`, by another column (third argument of `read`, fourth of `update`) or by conditions:

```php
$database->read('users', 'alice@example.com', 'email');
$database->read('users', ['email' => 'alice@example.com']);

$database->update('posts', ['status' => 'archived'], ['status' => 'draft']);
$database->delete('posts', [1, 2, 3]);                           // id IN (1, 2, 3)
$database->delete('sessions', ['updated_at < ?' => time() - 86400]);
```

## Selecting rows

`select($table, $fields, $where, $order, $limit)` returns a list of rows:

```php
$database->select('posts');                                       // every row and column
$database->select('posts', 'title');                              // one column
$database->select('posts', ['id', 'title']);                      // some columns

$database->select('posts', '*', ['status' => 'published']);       // WHERE status = 'published'
$database->select('posts', '*', [], 'id DESC');                   // ORDER BY id DESC
$database->select('posts', '*', [], 'id DESC', 10);               // LIMIT 10
$database->select('posts', '*', [], 'id DESC', '10 OFFSET 20');   // LIMIT 10 OFFSET 20
```

To split the rows in pages, see [[Pagination]].

### Conditions

The conditions of `select`, `read`, `update` and `delete`:

| Condition | SQL |
| --- | --- |
| `42` | `id = 42` |
| `[1, 2, 3]` | `id IN (1, 2, 3)`, an empty list matches no rows |
| `['status' => 'published']` | `status = 'published'` |
| `['status' => 'published', 'author_id' => 7]` | `status = 'published' AND author_id = 7` |
| `['views > ?' => 100]` | `(views > 100)` |
| `['status = ? OR status = ?' => ['draft', 'review']]` | `(status = 'draft' OR status = 'review')` |
| `['title LIKE ?' => '%php%', 'views > ?' => 100]` | `(title LIKE '%php%') AND (views > 100)` |
| `['status' => 'published', 'views > ?' => 100]` | `status = 'published' AND (views > 100)` |
| `['email = :email' => ['email' => 'alice@example.com']]` | `(email = 'alice@example.com')`, named placeholders |

Values are always passed as query parameters, so they're safe.
The SQL conditions of a call can use positional (`?`) or named (`:email`) placeholders, not both: combining them throws an `InvalidArgumentException`.

## Custom queries

`query($sql, $params)` runs any query and returns the rows, useful for joins and aggregations:

```php
$database->query(
    'SELECT posts.title, users.name FROM posts JOIN users ON users.id = posts.author_id WHERE posts.status = ?',
    ['published']
);

$database->query('SELECT status, COUNT(*) AS total FROM posts GROUP BY status');
// [['status' => 'draft', 'total' => '1'], ['status' => 'published', 'total' => '2']]

$database->query('CREATE TABLE IF NOT EXISTS tags (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
```

Named parameters work too: `query('SELECT * FROM users WHERE email = :email', ['email' => $email])`.
To version the schema changes, see [[Migrations]].

## Transactions

`transactional()` applies all the changes of the callback, or none of them when it throws an exception:

```php
$mf->post('/api/orders', function ($request, $database) {
    $order = $request->validate(['product_id' => ['required', 'integer'], 'quantity' => ['required', 'integer', 'min' => 1]]);

    return $database->transactional(function () use ($database, $order) {
        $id = $database->create('orders', $order);
        $database->query('UPDATE products SET stock = stock - ? WHERE id = ?', [$order['quantity'], $order['product_id']]);

        return ['id' => $id]; // the result of transactional()
    });
});
```

The same with the single operations:

```php
$database->beginTransaction();

try {
    $database->create('orders', $order);
    $database->query('UPDATE products SET stock = stock - 1 WHERE id = ?', [$order['product_id']]);

    $database->commit();
} catch (Throwable $exception) {
    $database->rollBack();

    throw $exception;
}

$database->inTransaction(); // false
```

## Supported databases

MySQL, MariaDB, PostgreSQL and SQLite are tested. On SQL Server `read` and the limit of `select` don't work, since `LIMIT` isn't supported.

Table and column names are quoted with the syntax of the database: backticks for MySQL, MariaDB and SQLite, brackets for SQL Server, double quotes (the SQL standard) for PostgreSQL and the others.
So reserved words like `order` or `group` can be used as names.

```php
$database->quoteIdentifier('order');       // `order` on MySQL, "order" on PostgreSQL
$database->quoteIdentifier('app.users');   // `app`.`users` on MySQL
```

Custom SQL (conditions, ordering, `query`) isn't changed, so use `quoteIdentifier` there too when needed.

## Details

-   `setPdo()` sets the `ERRMODE_EXCEPTION` and `ATTR_STRINGIFY_FETCHES` attributes of the connection
-   the connection is shared: [[Migrations]], [[Pagination]], `useDatabase()` and `rememberWithDatabase()` of the [[Authentication]] use it too
