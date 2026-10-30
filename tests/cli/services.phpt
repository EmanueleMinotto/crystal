--TEST--
CLI callbacks receive the services when the cli service is enabled
--FILE--
<?php

class Clock
{
    public function now() { return 'now'; }
}

$GLOBALS['argv'] = ['script.php', 'Alice'];

$mf = require_once(__DIR__.'/../../crystal.php');

$mf('cli')->enable();
$mf('site_name', 'My blog');
$mf('greeter', function ($greeting) { return $greeting; });

$mf(function ($name, $logger, Clock $clock, $site_name = 'default', $greeter = null, $times = 1, ...$rest) {
    echo $name, ' ', get_class($clock), ' ', $clock->now(), ' ', $site_name, ' ', var_export($greeter, true), ' ', $times, ' ', count($rest), PHP_EOL;
    var_dump(is_object($logger) && method_exists($logger, 'info'));
});

// priorities work as before
$mf(function () { echo 'last', PHP_EOL; }, 1);

?>
--EXPECT--
Alice Clock now default NULL 1 0
bool(true)
last
