The `listener` service, based on [PSR-14](https://www.php-fig.org/psr/psr-14/), decouples the parts of an application: a part dispatches an event, the other ones react to it.

```php
class UserRegistered
{
    public $user;

    public function __construct(array $user) { $this->user = $user; }
}

// the reactions, defined once (e.g. in index.php)
$mf('listener')->on(UserRegistered::class, function ($event) use ($mf) {
    $mf('mailer')->send([
        'to' => $event->user['email'],
        'subject' => 'Welcome!',
        'text' => 'Hello '.$event->user['name'],
    ]);

    return $event;
});

$mf('listener')->on(UserRegistered::class, function ($event) use ($mf) {
    $mf('logger')->info('New user {email}', ['email' => $event->user['email']]);

    return $event;
});

// the route doesn't need to know them
$mf->post('/register', function ($request, $database, $listener) {
    $user = $request->validate(['name' => ['required'], 'email' => ['required', 'email']]);
    $user['id'] = $database->create('users', $user);

    $listener->dispatch(new UserRegistered($user));

    return $user;
});
```

**Listeners must return the event**: the value returned by a listener is the event received by the next one, and the result of `dispatch()`.

## Listeners

Listeners receive 3 arguments: the event, its name and the listener service.
They're invoked in the order they're added.

```php
$listener = $mf('listener');

$listener->on('order:paid', function ($event, $name, $dispatcher) {
    // $name is "order:paid"
    return $event;
});

$listener->off('order:paid'); // removes all the listeners of the event
```

`$mf('listener')` always returns the same instance, so listeners can be added in one place and events dispatched from another one.

## Changing the event

Listeners can change the event, for example to collect values from the other parts of the application:

```php
class MenuBuild
{
    public $items = [];

    public function getEventName() { return 'menu:build'; }
}

$mf('listener')->on('menu:build', function ($event) {
    $event->items[] = ['url' => '/blog', 'label' => 'Blog'];

    return $event;
});

$items = $mf('listener')->dispatch(new MenuBuild())->items; // [['url' => '/blog', 'label' => 'Blog']]
```

## Names of the events

The name of an event is:

1.  the result of its `getCrystalEventName()` method, when it exists
2.  the result of its `getEventName()` method, when it exists
3.  the class name, for other objects (`UserRegistered::class` above)
4.  the event itself, when it isn't an object

## Events without objects

```php
$mf('listener')->on('cache:cleared', function ($event) {
    echo 'The cache was cleared';

    return $event;
});

$mf('listener')->dispatch('cache:cleared');
```

## Built-in events

[[Authentication]] dispatches `auth:login`, `auth:logout`, `auth:failed` and `auth:lockout`.
