Crystal provides an event listener based on [PSR-14](https://www.php-fig.org/psr/psr-14/).<br />
With the event listener you can emit events to be used (and manipulated) by listeners.

## Basic usage

```php
class EventClass {
    public $foo;

    public function getCrystalEventName() { return "my-event-name"; }
}

$mf(function () use ($mf) {
    $listener = $mf('listener');

    // the callbacks will receive 3 arguments:
    // - the event object
    // - the event name (in this case the string "my-event-name")
    // - the dispatcher object instance
    $listener->on("my-event-name", function ($event) {
        $event->foo = 'bar';

        return $event;
    });

    $event = new EventClass;
    $event = $listener->dispatch($event);

    var_dump($event); // $event->foo will be "bar"
});
```

What's the name of an event?

The event name is guessed with the following priority:
1. if it's an object and has the method `getCrystalEventName`, than that method is used to extract the event name
2. if it's an object and has the method `getEventName`, than that method is used to extract the event name
3. if it's an object, than the fully qualified class name will be used
4. otherwise the event itself is the name

## Usage without event

Events can be used even without an object

```php
$mf(function () use ($mf) {
    $listener = $mf('listener');

    $listener->on('test', function () { echo "I was dispatched"; });

    $listener->dispatch('test');

    $listener->off('test');
    $listener->dispatch('test');
});
```