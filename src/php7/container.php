<?php

/**
 * Simple container implementation based on PSR-11,
 * implemented without breaking crystal rules.
 *
 * @link https://www.php-fig.org/psr/psr-11/
 */
$deps['container'] = $deps['container'] ?? new class ($deps) {
    /**
     * @var array
     */
    private static $deps = array();

    /**
     * @var string[]
     */
    private static $aliases = array();

    /**
     * @param array $deps Dynamic set of dependencies.
     */
    public function __construct(array &$deps = array())
    {
        static::$deps = & $deps;
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

        if (isset(static::$aliases[$id])) {
            $id = static::$aliases[$id];
        }

        $service = static::$deps[$id];

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
        return isset(static::$deps[$id]) || isset(static::$aliases[$id]);
    }

    public function set(string $id, $value)
    {
        static::$deps[$id] = $value;
    }

    public function alias(string $alias, string $id)
    {
        static::$aliases[$alias] = $id;
    }

    public function make(string $fqcn, ...$args)
    {
        if (isset(static::$aliases[$fqcn])) {
            $fqcn = static::$aliases[$fqcn];
        }

        if ($this->has($fqcn)) {
            return $this->get($fqcn, ...$args);
        }

        if (!class_exists($fqcn)) {
            throw new Exception(sprintf('Class "%s" does not exist.', $fqcn));
        }

        $reflectionClass = new ReflectionClass($fqcn);
        $constructor = $reflectionClass->getConstructor();

        if (empty($constructor)) {
            return static::$deps[$fqcn] = $reflectionClass->newInstance();
        }

        $parameters = $constructor->getParameters();

        if (empty($parameters)) {
            return static::$deps[$fqcn] = $reflectionClass->newInstance();
        }

        $resolvedParams = array();

        foreach ($parameters as $parameter) {
            $type = (string) $parameter->getType();
            $name = $parameter->name;

            if (isset($args[$name])) {
                $resolvedParams[] = $args[$name];

                continue;
            }

            if ($this->has($type) || class_exists($type)) {
                $resolvedParams[] = $this->make($type);

                continue;
            }

            if ($parameter->isOptional()) {
                $parameter->getDefaultValue();
            }
        }

        return static::$deps[$fqcn] = $reflectionClass->newInstanceArgs($resolvedParams);
    }
};
