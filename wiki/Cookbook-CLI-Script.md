A command line script importing users from a CSV file into the database: each row is validated, the invalid ones are reported with their line, and `--dry-run` checks the file without importing it.
See [[CLI Usage]] for how command line scripts work.

```
my-app/
├── bin/
│   └── import-users.php
├── crystal.php
└── users.csv
```

`users.csv`:

```
name,email
Alice,alice@example.com
Bob,not-an-email
Carol,carol@example.com
```

`bin/import-users.php`:

```php
<?php

$mf = require __DIR__.'/../crystal.php';

$mf('database')::setPdo(new PDO('sqlite:'.__DIR__.'/../app.sqlite'));
$mf('database')->query('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, email TEXT NOT NULL UNIQUE)');

// Usage: php bin/import-users.php <file.csv> [--dry-run]
$mf(function ($file = null, $option = null) use ($mf) {
    if (is_null($file) || !is_readable($file)) {
        fwrite(STDERR, 'Usage: php bin/import-users.php <file.csv> [--dry-run]'.PHP_EOL);
        exit(1);
    }

    $dryRun = '--dry-run' === $option;
    $rules = ['name' => ['required', 'string', 'max' => 100], 'email' => ['required', 'email']];

    $handle = fopen($file, 'r');
    $columns = fgetcsv($handle, 0, ',', '"', '\\');
    $line = 1;
    $imported = 0;
    $invalid = 0;

    while (false !== ($row = fgetcsv($handle, 0, ',', '"', '\\'))) {
        ++$line;
        $user = array_combine($columns, $row);
        $errors = $mf('validator')->check($user, $rules);

        if (!empty($errors)) {
            ++$invalid;
            echo 'Line '.$line.': '.implode(' ', array_merge(...array_values($errors))).PHP_EOL;

            continue;
        }

        if (!$dryRun) {
            $mf('database')->create('users', ['name' => $user['name'], 'email' => $user['email']]);
        }

        ++$imported;
    }

    fclose($handle);

    echo ($dryRun ? 'Valid' : 'Imported').': '.$imported.', invalid: '.$invalid.PHP_EOL;

    // a different exit code when some rows are invalid, useful in other scripts
    exit($invalid > 0 ? 2 : 0);
});
```

## Running it

```bash
$ php bin/import-users.php users.csv --dry-run
Line 3: The email field must be a valid email address.
Valid: 2, invalid: 1

$ php bin/import-users.php users.csv
Line 3: The email field must be a valid email address.
Imported: 2, invalid: 1

$ echo $?
2
```

## How it works

-   the arguments of the script are the parameters of the callback, with default values for the optional ones
-   the callbacks of the command line don't receive the services as parameters, so they're read with `$mf()`
-   the [[Validation]] works on any array, like the rows of the file: `check()` returns the errors instead of stopping the script
-   `exit()` sets the exit code: `0` for success, `1` for a wrong usage and `2` for invalid rows

## Going further

-   wrap the import in a [transaction](Database#transactions), so a failure in the middle leaves the table as it was
-   share the configuration with the website through a `bootstrap.php` file, like the [website with login](Cookbook-Website-With-Login)
