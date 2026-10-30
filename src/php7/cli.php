<?php

/**
 * Command line scripts, enabled by `enable()`.
 *
 * When enabled, the callbacks of the command line:
 * - receive the arguments of the script without the options (like `--force`
 *   or `--limit=10`), which are read with `option()`
 * - receive the services as parameters, like the route callbacks
 *
 * Callbacks defined before `enable()` receive the arguments as they are.
 */
$deps['cli'] = new class ($deps) {
    /**
     * Dynamic set of dependencies, used to inject the services.
     *
     * @var array
     */
    private $deps;

    /**
     * @var bool
     */
    private $enabled = false;

    /**
     * @param array $deps Dynamic set of dependencies
     */
    public function __construct(array &$deps = [])
    {
        $this->deps = &$deps;
    }

    public function enable(bool $enabled = true)
    {
        $this->enabled = $enabled;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Arguments of the script, without the script name and the options.
     *
     * @return string[]
     */
    public function arguments(): array
    {
        return $this->parse()[0];
    }

    /**
     * Options of the script: `--name` is true, `--name=value` is the value.
     */
    public function options(): array
    {
        return $this->parse()[1];
    }

    /**
     * @param string $name    Name of the option, without the dashes
     * @param mixed  $default Value returned when the option is missing
     *
     * @return mixed
     */
    public function option(string $name, $default = null)
    {
        return $this->options()[$name] ?? $default;
    }

    /**
     * Invoke a callback with the arguments of the script, the parameters
     * without an argument receive the service with the same name or, for
     * classes, an instance made by the container.
     *
     * @param callable $callback Callback of the command line
     *
     * @throws ArgumentCountError When a required parameter is neither an argument nor a service
     *
     * @return mixed
     */
    public function invoke(callable $callback)
    {
        $arguments = $this->arguments();
        $values = [];

        if ((is_string($callback) && function_exists($callback)) || $callback instanceof Closure) {
            $reflector = new ReflectionFunction($callback);
        } elseif (is_array($callback)) {
            $reflector = new ReflectionMethod($callback[0], $callback[1]);
        } elseif (is_object($callback)) {
            $reflector = new ReflectionMethod($callback, '__invoke');
        } else {
            $reflector = new ReflectionMethod($callback);
        }

        foreach ($reflector->getParameters() as $position => $parameter) {
            if ($parameter->isVariadic()) {
                break;
            }

            if (array_key_exists($position, $arguments)) {
                $values[] = $arguments[$position];

                continue;
            }

            $value = $this->service($parameter);

            if (!is_null($value)) {
                $values[] = $value;
            } elseif ($parameter->isDefaultValueAvailable()) {
                // the next parameters can still be injected
                $values[] = $parameter->getDefaultValue();
            } elseif (!$parameter->isOptional() && class_exists('ArgumentCountError')) {
                throw new ArgumentCountError(sprintf(
                    'Too few arguments to the command line callback: $%s is neither an argument of the script nor a service.',
                    $parameter->name
                ));
            } else {
                break;
            }
        }

        // arguments without a parameter are passed too, like func_get_args() expects
        return call_user_func_array($callback, array_merge($values, array_slice($arguments, count($values))));
    }

    /**
     * Service for a parameter, null when there's none: only objects are
     * injected, factories with required arguments and settings aren't.
     *
     * @return null|object
     */
    private function service(ReflectionParameter $parameter)
    {
        $name = $parameter->name;
        $container = $this->deps['container'] ?? null;
        $container = is_object($container) && method_exists($container, 'has') && method_exists($container, 'get') ? $container : null;
        $value = null;

        if (array_key_exists($name, $this->deps)) {
            $value = $this->deps[$name];

            if ($value instanceof Closure) {
                if ((new ReflectionFunction($value))->getNumberOfRequiredParameters() > 0) {
                    return null;
                }

                $value = is_null($container) ? $value() : $container->get($name);
            } elseif (!is_object($value) || is_callable($value)) {
                return null;
            }
        } elseif (!is_null($container) && $container->has($name)) {
            $value = $container->get($name);
        }

        $type = $parameter->getType();
        $class = is_null($type) || (method_exists($type, 'isBuiltin') && $type->isBuiltin())
            ? ''
            : (method_exists($type, 'getName') ? $type->getName() : (string) $type);

        if (!is_object($value) && '' !== $class && !is_null($container) && method_exists($container, 'make')) {
            if ($container->has($class) || class_exists($class)) {
                $value = $container->make($class);
            }
        }

        return is_object($value) ? $value : null;
    }

    /**
     * Split the arguments of the script from the options, arguments after
     * `--` and starting with a single dash (like `-5`) aren't options.
     *
     * @return array [arguments, options]
     */
    private function parse(): array
    {
        $arguments = [];
        $options = [];
        $ended = false;

        foreach (array_slice($GLOBALS['argv'] ?? [], 1) as $argument) {
            if ($ended || 0 !== strpos($argument, '--')) {
                $arguments[] = $argument;
            } elseif ('--' === $argument) {
                $ended = true;
            } else {
                // repeated options keep the last value
                $option = explode('=', substr($argument, 2), 2);
                $options[$option[0]] = $option[1] ?? true;
            }
        }

        return [$arguments, $options];
    }
};
