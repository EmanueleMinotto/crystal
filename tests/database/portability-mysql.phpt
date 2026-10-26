--TEST--
Portable SQL (MySQL)
--SKIPIF--
<?php
if (!extension_loaded('pdo_mysql')) {
    die('Skip: pdo_mysql is required');
}
if (!getenv('CRYSTAL_MYSQL_DSN')) {
    die('Skip: CRYSTAL_MYSQL_DSN environment variable is not set');
}
?>
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');
$run = require(__DIR__.'/portability.inc');

$mf(function () use ($mf, $run) {
    $run($mf, new PDO(getenv('CRYSTAL_MYSQL_DSN'), getenv('CRYSTAL_MYSQL_USER') ?: 'root', getenv('CRYSTAL_MYSQL_PASS') ?: ''));
});

?>
--EXPECT--
create: [1,2,3]
select by column: [{"id":"2","group":"g2"}]
select in: [{"id":"1"},{"id":"3"}]
read: g3
read by column: 1
update in: [{"group":"updated"},{"group":"updated"},{"group":"g3"}]
select mixed: [{"id":"2"}]
paginate: [[{"id":"2"}],2,2]
delete nothing: 3
delete in: [{"id":"2"}]
migrate: ["1"] ["1"]
rollback: ["1"] 1
remember: 1
cache: true true
