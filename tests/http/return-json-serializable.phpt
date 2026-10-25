--TEST--
JsonSerializable objects returned by route callbacks are JSON responses
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/user';
$_SERVER['SCRIPT_NAME'] = '/';

$mf('/user', function () {
    echo "output before, ";
}, 'GET', 0);

$mf('/user', function () {
    echo 'output of the callback, ';

    return new class () implements JsonSerializable {
        #[\ReturnTypeWillChange]
        public function jsonSerialize()
        {
            return ['name' => 'Alice'];
        }
    };
}, 'GET', 1);

// strings and other values aren't responses
$mf('/user', function () {
    return 'ignored';
}, 'GET', 2);

?>
--EXPECT--
output before, output of the callback, {"name":"Alice"}
