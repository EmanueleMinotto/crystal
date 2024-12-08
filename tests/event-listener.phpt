--TEST--
Event listener
--SKIPIF--
<?php

if (PHP_MAJOR_VERSION < 7) {
    die('Skip: PHP 7+ is required');
}

?>
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

class Event {
    public $foo;
}

$mf(function () use ($mf) {
    $listener = $mf('listener');

    $listener->on('test', function () { echo "test1\n"; });
    $listener->on('test', function () { echo "test2\n"; });

    $listener->on('Event', function ($event) {
        echo "dispatching\n";

        $event->foo = 'bar';

        return $event;
    });

    $event = new Event;
    $event = $listener->dispatch($event);

    var_dump($event);

    $listener->dispatch('test');

    $listener->off('test');
    $listener->dispatch('test');
});

?>
--EXPECT--
dispatching
object(Event)#24 (1) {
  ["foo"]=>
  string(3) "bar"
}
test1
test2
