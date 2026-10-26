The `migrations` service versions the changes of the database schema, so every copy of the application (your computer, the server, ...) can be updated in the same way.
It uses the connection of the [[Database]] service.

## Migration files

Each migration is a file in a directory: the file name (without `.php`) is the version, the file returns the `up` callback or an array with the `up` and `down` callbacks.
Both callbacks receive the [[Database]] service.

```
migrations/
├── 20260101000000_create_posts.php
└── 20260102000000_create_comments.php
```

`migrations/20260101000000_create_posts.php`:

```php
<?php

return [
    'up' => function ($db) {
        $db->query('CREATE TABLE posts (id INTEGER PRIMARY KEY, title TEXT NOT NULL)');
    },
    'down' => function ($db) {
        $db->query('DROP TABLE posts');
    },
];
```

`migrations/20260102000000_create_comments.php`:

```php
<?php

return [
    'up' => function ($db) {
        $db->query('CREATE TABLE comments (id INTEGER PRIMARY KEY, post_id INTEGER NOT NULL, text TEXT NOT NULL)');
    },
    'down' => function ($db) {
        $db->query('DROP TABLE comments');
    },
];
```

A file can return only the `up` callback, then the migration can't be reverted:

```php
<?php

return function ($db) {
    $db->create('posts', ['title' => 'Welcome!']);
};
```

Versions are sorted using the [natural order](https://www.php.net/manual/en/function.strnatcmp.php), timestamps keep the order even when migrations are written in parallel branches.

## Applying the migrations

### From the command line

A [[CLI Usage]] script applies, reverts and lists the migrations.

`bin/migrate.php`:

```php
<?php

$mf = require __DIR__.'/../crystal.php';

$mf('database')::setPdo(new PDO('sqlite:'.__DIR__.'/../app.sqlite'));

// Usage: php bin/migrate.php [migrate|rollback|status]
$mf(function ($command = 'migrate') use ($mf) {
    $migrations = $mf('migrations');
    $migrations->load(__DIR__.'/../migrations');

    if ('status' === $command) {
        echo 'Applied: '.implode(', ', $migrations->applied()).PHP_EOL;
        echo 'Pending: '.implode(', ', $migrations->pending()).PHP_EOL;

        return;
    }

    $versions = 'rollback' === $command ? $migrations->rollback() : $migrations->migrate();

    echo implode(PHP_EOL, $versions).PHP_EOL;
});
```

```bash
$ php bin/migrate.php
20260101000000_create_posts
20260102000000_create_comments

$ php bin/migrate.php rollback
20260102000000_create_comments
```

### From a protected route

On hostings without a command line (see [[When to use crystal]]), use a route reserved to the administrators, and remove it afterwards:

```php
$mf->get('/admin/migrate', $mf('middleware')->wrap(function ($migrations) {
    $migrations->load(__DIR__.'/migrations');

    return ['applied' => $migrations->migrate()];
}, $mf('auth')->middleware('admin')));
```

## Methods

```php
$migrations = $mf('migrations');

$migrations->migrate();                              // apply the pending migrations, returns their versions
$migrations->migrate('20260101000000_create_posts'); // apply them up to a version (included)

$migrations->rollback();                             // revert the latest applied migration
$migrations->rollback(3);                            // revert the latest 3

$migrations->applied();                              // applied versions
$migrations->pending();                              // pending versions
```

A `RuntimeException` is thrown when reverting a migration without the `down` callback, or a migration not registered anymore.

### Migrations without files

```php
$migrations->add('20260101000000_create_users', function ($db) {
    $db->query('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT NOT NULL UNIQUE)');
}, function ($db) {
    $db->query('DROP TABLE users');
});
```

### The versions table

Applied versions are stored in the `crystal_migrations` table, created automatically on first use:

```php
$migrations->setTable('my_migrations');
```

## Failures

Each migration runs in a [transaction](Database#transactions): if the callback throws, the changes are reverted, the migration isn't marked as applied and the exception is thrown again, stopping the next migrations.

Some databases (e.g. MySQL) [implicitly commit](https://dev.mysql.com/doc/refman/8.0/en/implicit-commit.html) the transaction when a schema statement like `CREATE TABLE` is executed: the changes made before the failure can't be reverted, so prefer one schema change per migration.
