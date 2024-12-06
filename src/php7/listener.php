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
    public function dispatch($event)
    {
        $eventName = $this->getEventName($event);
        $listeners = $this->getListenersForEvent($event);

        foreach ($listeners as $listener) {
            $event = $listener($event, $eventName, $this);
        }

        return $event;
    }

    public function on(string $eventName, callable $callback)
    {
        if (empty($this->listeners[$eventName])) {
            $this->listeners[$eventName] = array();
        }

        $this->listeners[$eventName][] = $callback;
    }

    public function off(string $eventName)
    {
        $this->listeners[$eventName] = array();
    }

    /**
     * @param object $event An event for which to return the relevant listeners.
     *
     * @return iterable<callable> An iterable (array, iterator, or generator) of callables. Each callable MUST be type-compatible with $event.
     */
    public function getListenersForEvent($event)
    {
        return $this->listeners[$this->getEventName($event)] ?? array();
    }

    private function getEventName($event)
    {
        if (!is_object($event)) {
            return $event;
        }

        if (method_exists($event, 'getCrystalEventName')) {
            return $event->getCrystalEventName();
        }

        if (method_exists($event, 'getEventName')) {
            return $event->getEventName();
        }

        return get_class($event);
    }
};
