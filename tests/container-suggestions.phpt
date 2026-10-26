--TEST--
Similar identifiers suggested for missing container entries
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

$mf('container')->alias('db', 'database');

foreach (['databse', 'Sesion', 'xyz', 'id', 'dbb'] as $id) {
    try {
        $mf($id);
    } catch (Exception $exception) {
        echo $exception->getMessage()."\n";
    }
}

?>
--EXPECT--
Entry "databse" not found, did you mean "database"?
Entry "Sesion" not found, did you mean "session"?
Entry "xyz" not found.
Entry "id" not found.
Entry "dbb" not found, did you mean "db"?
