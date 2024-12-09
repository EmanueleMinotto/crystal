<?php

/**
 * Simple container implementation based on PSR-11,
 * implemented without breaking crystal rules.
 *
 * @link https://www.php-fig.org/psr/psr-11/
 */
$deps['container'] = new class ($deps) {
    /**
     * @var array
     */
    private static $deps = array();

    /**
     * @param array $deps Dynamic set of dependencies.
     */
    public function __construct(array &$deps = array())
    {
        self::$deps = $deps;
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id   Identifier of the entry to look for.
     * @param mixed  $args Optional arguments for the service factory.
     *
     * @throws Exception No entry was found for **this** identifier.
     *
     * @return mixed Entry.
     */
    public function get(string $id, ...$args)
    {
        if (!$this->has($id)) {
            throw new Exception(sprintf('Entry "%s" not found.', $id));
        }

        $service = self::$deps[$id];

        return is_callable($service)
            ? call_user_func_array($service, $args)
            : $service;
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     *
     * `has($id)` returning true does not mean that `get($id)` will not throw an exception.
     * It does however mean that `get($id)` will not throw a `NotFoundExceptionInterface`.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset(self::$deps[$id]);
    }
};
