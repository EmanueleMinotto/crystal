--TEST--
Event listener
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
--EXPECTF--
dispatching
object(Event)#%d (1) {
  ["foo"]=>
  string(3) "bar"
}
test1
test2
