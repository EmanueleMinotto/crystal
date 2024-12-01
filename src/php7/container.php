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
     * @param string $id Identifier of the entry to look for.
     *
     * @throws Exception No entry was found for **this** identifier.
     *
     * @return mixed Entry.
     */
    public function get(string $id)
    {
        if (empty(self::$deps[$id])) {
            $exception = new class () extends \Exception {
            };

            throw $exception;
        }

        return self::$deps[$id];
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
        return !empty(self::$deps[$id]);
    }
};
