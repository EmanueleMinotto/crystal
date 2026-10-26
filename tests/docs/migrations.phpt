--TEST--
Wiki: the migrations command of the Migrations page
--SKIPIF--
<?php if (!extension_loaded('pdo_sqlite')) die('skip pdo_sqlite is required'); ?>
--FILE--
<?php

require __DIR__.'/helpers.php';

$app = docs_app('Migrations');

echo docs_cli($app, 'bin/migrate.php', ['status']);
echo docs_cli($app, 'bin/migrate.php');
echo docs_cli($app, 'bin/migrate.php', ['rollback']);
echo docs_cli($app, 'bin/migrate.php', ['status']);

?>
--EXPECT--
Applied:
Pending: 20260101000000_create_posts, 20260102000000_create_comments
20260101000000_create_posts
20260102000000_create_comments
20260102000000_create_comments
Applied: 20260101000000_create_posts
Pending: 20260102000000_create_comments
