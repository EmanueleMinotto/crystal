Most of the server-side applications based on PHP are based on a database, so crystal provides a simple utility to work with it.<br />
The utility is accessible from `$mf('database')` but **only for PHP 7 or newer versions**, because it's an anonymous class.

In this case the framework can't guess where is your database, so you need to setup at least the database connection.<br/>
The connection can be done with the `setPdo` static method.

```php
$mf(function () use ($mf) {
    $db = $mf('database');
    $db::setPdo(new PDO('sqlite::memory:'));
});
```

Once done, you are ready to interact with the database!

## Basic usage

The basic usage is through CRUD operations: `create`, `read`, `update` and `delete`.

```php
$mf(function () use ($mf) {
    $db = $mf('database');
    $db::setPdo(new PDO('sqlite::memory:'));

    // we need to create a table to show the interaction
    // the `query` method accepts as 2nd argument an array of parameters, which will be passed to the query
    // and returns an array of all the output, this is useful for SELECTs with joins
    $db->query('CREATE TABLE my_table (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');

    // create a first row and store the inserted id in a variable
    // for example in MySQL you don't need to pass an id if it's
    // auto increment, but you'll need to know what's inserted
    $createdId = $db->create('my_table', [
        'id' => 1,
        'name' => 'foo',
    ]);

    var_dump($db->read('my_table', $createdId));
    // we'll receive an array with columns as keys and associated values
    // array(2) {
    //  ["id"]=> string(1) "1"
    //  ["name"]=> string(3) "foo"
    // }

    // will update the row with id=1 setting the column name to "bar"
    $db->update('my_table', [
        'name' => 'bar',
    ], 1);

    // this will delete the record with id=1
    $db->delete('crystal_test', 1);
});
```


## Advanced usage

Excluding the basic CRUD usage, there are more details for an advanced usage.<br />
For example you can use the `select` method that's a mini query builder and many methods allow an advanced where building.

```php
$mf(function () use ($mf) {
    $db = $mf('database');
    $db::setPdo(new PDO('sqlite::memory:'));

    // select all rows in the table my_table
    $rows = $db->select('my_table');

    // select only the column "id", by default selects "*"
    $rows = $db->select('my_table', 'id');
    // select only specific columns
    $rows = $db->select('my_table', ['v1', 'v2']);

    // select by column and value
    $db->select('my_table', null, [
        'column' => 'value',
    ]);

    // select by custom query
    $db->select('my_table', null, [
        'column1 = ? OR column2 = ?' => ['value1', 'value2'],
    ]);

    // select by multiple custom conditions
    $db->select('my_table', null, [
        'column1 = ? OR column1 = ?' => ['value1', 'value2'],
        'column3 LIKE ?' => '%value3%',
    ]);

    // select sorted
    $db->select('my_table', ['id', 'column1'], [], 'id DESC');

    // select limited
    $db->select('my_table', ['id', 'column1'], [], null, 2);
});
```

## Transactions

Using the [database transactions](https://en.wikipedia.org/wiki/Database_transaction), you can group operations without applying the changes if one of these operations fails.


```php
$mf(function () use ($mf) {
    $db = $mf('database');
    $db::setPdo(new PDO('sqlite::memory:'));

    $db->transactional(function () use ($db) {
        $db->create('my_table', [
            'id' => 1,
            'name' => 'test',
        ]);

        // $db->inTransaction() === true

        $db->read('not_existing_table', 1);
    });
});
```

In this case, the row `(1, 'test')` won't be inserted in the table `my_table` (even if it exists, we created and used it in previous examples) because the next operations will fail, this will rollback all the changes.

The same grouping can be done with the single operations.

```php
$mf(function () use ($mf) {
    $db = $mf('database');
    $db::setPdo(new PDO('sqlite::memory:'));

    $db->beginTransaction();

    try {
        $db->create('my_table', [
            'id' => 1,
            'name' => 'test',
        ]);

        // $db->inTransaction() === true

        $db->read('not_existing_table', 1);

        $db->commit();
    } catch (Throwable $exception) {
        $db->rollBack();

        throw $exception;
    }
});
```