--TEST--
PHP warnings converted to exceptions
--INI--
display_errors=1
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/';

$errors = $mf('errors');
$errors->enable();
$errors->setReporter(function () {});

try {
    trigger_error('a warning', E_USER_WARNING);
} catch (ErrorException $exception) {
    var_dump($exception->getMessage(), $exception->getSeverity() === E_USER_WARNING);
}

// silenced errors and deprecations aren't converted
var_dump(@file_get_contents(__DIR__.'/missing'));
$previous = error_reporting(E_ALL & ~E_USER_DEPRECATED);
trigger_error('a deprecation', E_USER_DEPRECATED);
error_reporting($previous);
set_error_handler(function ($severity, $message) {
    echo "not converted: $message\n";
}, E_USER_DEPRECATED);
trigger_error('a deprecation', E_USER_DEPRECATED);
restore_error_handler();

// excluded by error_reporting
error_reporting(E_ALL & ~E_USER_NOTICE);
trigger_error('a notice', E_USER_NOTICE);
echo "done\n";

?>
--EXPECT--
string(9) "a warning"
bool(true)
bool(false)
not converted: a deprecation
done
