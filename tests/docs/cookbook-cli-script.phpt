--TEST--
Wiki: the command line script of the Cookbook
--SKIPIF--
<?php if (!extension_loaded('pdo_sqlite')) die('skip pdo_sqlite is required'); ?>
--FILE--
<?php

require __DIR__.'/helpers.php';

$app = docs_app('Cookbook-CLI-Script');

echo docs_cli($app, 'bin/import-users.php');
echo docs_cli($app, 'bin/import-users.php', [$app.'/users.csv', '--dry-run']);
echo docs_cli($app, 'bin/import-users.php', [$app.'/users.csv']);

$pdo = new PDO('sqlite:'.$app.'/app.sqlite');
echo implode(', ', $pdo->query('SELECT email FROM users ORDER BY id')->fetchAll(PDO::FETCH_COLUMN))."\n";

?>
--EXPECT--
Usage: php bin/import-users.php <file.csv> [--dry-run]
exit code: 1
Line 3: The email field must be a valid email address.
Valid: 2, invalid: 1
exit code: 2
Line 3: The email field must be a valid email address.
Imported: 2, invalid: 1
exit code: 2
alice@example.com, carol@example.com
