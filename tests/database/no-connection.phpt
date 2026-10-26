--TEST--
Database service used without a connection
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

try {
    $mf('database')->select('posts');
} catch (LogicException $exception) {
    echo get_class($exception).': '.$exception->getMessage()."\n";
}

?>
--EXPECT--
LogicException: The database service has no connection, set it with $mf('database')::setPdo(new PDO(...)).
