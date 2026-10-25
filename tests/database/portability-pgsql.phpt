--TEST--
Portable SQL (PostgreSQL)
--SKIPIF--
<?php
if (!extension_loaded('pdo_pgsql')) {
    die('Skip: pdo_pgsql is required');
}
if (!getenv('CRYSTAL_PGSQL_DSN')) {
    die('Skip: CRYSTAL_PGSQL_DSN environment variable is not set');
}
?>
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');
$run = require(__DIR__.'/portability.inc');

$mf(function () use ($mf, $run) {
    $run($mf, new PDO(getenv('CRYSTAL_PGSQL_DSN'), getenv('CRYSTAL_PGSQL_USER') ?: 'postgres', getenv('CRYSTAL_PGSQL_PASS') ?: ''));
});

?>
--EXPECT--
create: [1,2,3]
select by column: [{"id":"2","group":"g2"}]
select in: [{"id":"1"},{"id":"3"}]
read: g3
read by column: 1
update in: [{"group":"updated"},{"group":"updated"},{"group":"g3"}]
paginate: [[{"id":"2"}],2,2]
delete nothing: 3
delete in: [{"id":"2"}]
migrate: ["1"] ["1"]
rollback: ["1"] 1
remember: 1
cache: true true
