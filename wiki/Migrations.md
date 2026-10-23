Database schemas evolve together with the code, so crystal provides a migrations system built on top of the [[Database]] utility.<br />
The utility is accessible from `$mf('migrations')`.

Migrations use the same connection of the database utility, so the connection must be set first with the `setPdo` static method.

```php
$mf(function () use ($mf) {
    $db = $mf('database');
    $db::setPdo(new PDO('sqlite::memory:'));

    $migrations = $mf('migrations');
});
```

## Basic usage

Each migration has a unique version and an `up` callback applying the changes; optionally a `down` callback can revert them.<br />
Both callbacks receive the database utility as first argument.

```php
$mf(function () use ($mf) {
    $db = $mf('database');
    $db::setPdo(new PDO('sqlite::memory:'));

    $migrations = $mf('migrations');

    $migrations->add('20240101000000_create_users', function ($db) {
        $db->query('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
    }, function ($db) {
        $db->query('DROP TABLE users');
    });

    $migrations->add('20240102000000_seed_users', function ($db) {
        $db->create('users', ['name' => 'foo']);
    });

    // apply all the pending migrations, returns the applied versions
    $migrations->migrate();

    // or apply pending migrations up to a specific version (included)
    $migrations->migrate('20240101000000_create_users');
});
```

Versions are sorted using the [natural order](https://www.php.net/manual/en/function.strnatcmp.php) algorithm, so `2` comes before `10`; timestamps are the suggested format because they keep the order even when migrations are written in parallel branches.

Applied versions are stored in the `crystal_migrations` table, created automatically on first use. The table name can be changed with `setTable`.

```php
$migrations->setTable('my_migrations');
```

## Migration files

Migrations can be stored in a directory, one file per migration.<br />
The file name (without the `.php` extension) is the version and the file must return the `up` callback or an array with `up` and `down` keys.

```php
// migrations/20240101000000_create_users.php
return [
    'up' => function ($db) {
        $db->query('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
    },
    'down' => function ($db) {
        $db->query('DROP TABLE users');
    },
];
```

```php
// migrations/20240102000000_seed_users.php
return function ($db) {
    $db->create('users', ['name' => 'foo']);
};
```

```php
$mf(function () use ($mf) {
    $migrations = $mf('migrations');
    $migrations->load(__DIR__.'/migrations');
    $migrations->migrate();
});
```

Combined with the [[CLI Usage]], this makes a simple migrations command:

```php
// bin/migrate.php
$mf = require_once(__DIR__.'/../crystal.php');

$mf(function ($command = 'migrate') use ($mf) {
    $mf('database')::setPdo(new PDO('sqlite:'.__DIR__.'/../database.sqlite'));

    $migrations = $mf('migrations');
    $migrations->load(__DIR__.'/../migrations');

    $versions = 'rollback' === $command
        ? $migrations->rollback()
        : $migrations->migrate();

    echo implode(PHP_EOL, $versions).PHP_EOL;
});
```

## Rollback and status

```php
$mf(function () use ($mf) {
    $migrations = $mf('migrations');

    // revert the latest applied migration, returns the reverted versions
    $migrations->rollback();

    // revert the latest 3 applied migrations
    $migrations->rollback(3);

    // list applied and pending versions
    $migrations->applied();
    $migrations->pending();
});
```

A `RuntimeException` is thrown when reverting a migration without the `down` callback or a migration not registered anymore.

## Failures

Each migration runs in a [transaction](Database#transactions): if the callback throws, the changes are reverted, the migration isn't marked as applied and the exception is thrown again, stopping the next migrations.

Some databases (e.g. MySQL) [implicitly commit](https://dev.mysql.com/doc/refman/8.0/en/implicit-commit.html) the transaction when a schema statement like `CREATE TABLE` is executed, in this case the changes made before the failure can't be reverted, so prefer one schema change per migration.
