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
    private static $deps = [];

    /**
     * @var string[]
     */
    private static $aliases = [];

    /**
     * @param array $deps Dynamic set of dependencies.
     */
    public function __construct(array &$deps = [])
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
            // the closest identifier, like "database" for "databse", short
            // identifiers would be similar to too many entries
            $suggestion = null;
            $distance = 3;

            foreach (array_merge(array_keys(static::$deps), array_keys(static::$aliases)) as $candidate) {
                $candidateDistance = is_string($candidate) && strlen($candidate) <= 255 && strlen($id) <= 255
                    ? levenshtein(strtolower($id), strtolower($candidate))
                    : -1;

                if ($candidateDistance >= 0 && $candidateDistance < $distance && $candidateDistance * 2 < strlen($id)) {
                    $suggestion = $candidate;
                    $distance = $candidateDistance;
                }
            }

            throw new Exception(is_null($suggestion)
                ? sprintf('Entry "%s" not found.', $id)
                : sprintf('Entry "%s" not found, did you mean "%s"?', $id, $suggestion));
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

    /**
     * Define a service created on first use, then shared: every call returns
     * the same instance.
     *
     * @param string   $id      Identifier of the service.
     * @param callable $factory Function creating the service.
     */
    public function share(string $id, callable $factory)
    {
        $created = false;
        $instance = null;

        $this->set($id, function () use ($factory, &$created, &$instance) {
            if (!$created) {
                $instance = $factory();
                $created = true;
            }

            return $instance;
        });
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

        $resolvedParams = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            $type = is_null($type) ? '' : (method_exists($type, 'getName') ? $type->getName() : (string) $type);

            $name = $parameter->name;

            if (isset($args[$name])) {
                $resolvedParams[] = $args[$name];

                continue;
            }

            if ('' !== $type && ($this->has($type) || class_exists($type))) {
                $resolvedParams[] = $this->make($type);

                continue;
            }

            // the default value keeps the next arguments in their position
            if ($parameter->isDefaultValueAvailable()) {
                $resolvedParams[] = $parameter->getDefaultValue();
            }
        }

        return static::$deps[$fqcn] = $reflectionClass->newInstanceArgs($resolvedParams);
    }
};
