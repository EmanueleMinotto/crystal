<?php

$deps['listener'] = new class () {
    /**
     * @var callable[]
     */
    private $listeners = array();

    /**
     * Provide all relevant listeners with an event to process.
     *
     * @param object $event The object to process.
     *
     * @return object The Event that was passed, now modified by listeners.
     */
    public function dispatch(object $event)
    {
        $eventName = $this->getEventName($event);
        $listeners = $this->getListenersForEvent($event);

        foreach ($listeners as $listener) {
            $event = $listener($event, $eventName, $this);
        }

        return $event;
    }

    public function on(string $event, callable $callback)
    {
        if (empty($this->listeners[$event])) {
            $this->listeners[$event] = array();
        }

        $this->listeners[$event][] = $callback;
    }

    public function off(string $event)
    {
        $this->listeners[$event] = array();
    }

    /**
     * @param object $event An event for which to return the relevant listeners.
     *
     * @return iterable<callable> An iterable (array, iterator, or generator) of callables. Each callable MUST be type-compatible with $event.
     */
    public function getListenersForEvent(object $event)
    {
        return $this->listeners[$this->getEventName($event)] ?? array();
    }

    private function getEventName(object $event)
    {
        if (method_exists($event, 'getCrystalEventName')) {
            return $event->getCrystalEventName();
        }

        if (method_exists($event, 'getEventName')) {
            return $event->getEventName();
        }

        return $event::class;
    }
};
