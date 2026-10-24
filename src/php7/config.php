<?php

/**
 * Exposes a configuration object via $deps['config'] supporting:
 * - load, get, set, has, all (keys use the dot notation, e.g. "database.host")
 * - loadEnv, env (environment variables and .env files)
 *
 * Configuration sources: arrays, .php (returning an array), .json and .ini files.
 *
 * @see https://www.php.net/manual/en/function.getenv.php
 * @see https://www.php.net/manual/en/function.parse-ini-file.php
 */
$deps['config'] = new class () {
    /**
     * Loaded configuration values.
     *
     * @var array
     */
    private $values = [];

    /**
     * Load configuration values, merged recursively into the existing ones.
     *
     * Associative arrays are merged key by key, lists and scalars are replaced.
     *
     * @param  array|string              $source Configuration array or file path (.php, .json, .ini)
     * @throws \InvalidArgumentException If the file type is unsupported or doesn't contain an array
     * @throws \RuntimeException         If the file is not readable or can't be parsed
     */
    public function load($source)
    {
        if (is_string($source)) {
            $source = $this->read($source);
        }

        if (!is_array($source)) {
            throw new \InvalidArgumentException('Configuration source must be an array or a file path');
        }

        $this->values = $this->merge($this->values, $source);
    }

    /**
     * Retrieve a configuration value.
     *
     * @param  string $key     Dot notation key (e.g. "database.host")
     * @param  mixed  $default Value returned when the key doesn't exist
     * @return mixed
     */
    public function get($key, $default = null)
    {
        $value = $this->values;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Store a configuration value, intermediate levels are created when missing.
     *
     * @param string $key   Dot notation key (e.g. "database.host")
     * @param mixed  $value Value to store
     */
    public function set($key, $value)
    {
        $target = &$this->values;

        foreach (explode('.', $key) as $segment) {
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }

        $target = $value;
    }

    /**
     * Check if a configuration value exists (null values included).
     *
     * @param  string $key Dot notation key (e.g. "database.host")
     * @return bool
     */
    public function has($key)
    {
        // a new object is identical only to itself, so it is a safe "missing" marker
        $missing = new \stdClass();

        return $this->get($key, $missing) !== $missing;
    }

    /**
     * Retrieve every configuration value.
     *
     * @return array
     */
    public function all()
    {
        return $this->values;
    }

    /**
     * Load environment variables from a .env file.
     *
     * Supported syntax:
     *   # comment
     *   NAME=value               # inline comment (only when preceded by a space)
     *   export NAME=value
     *   NAME='literal $value'    — no escapes nor interpolation
     *   NAME="line\nbreak ${X}"  — escapes (\n \r \t \" \\ \$) and interpolation,
     *                              the value can span multiple lines
     *   NAME=${X}/path           — interpolation in unquoted values too
     *
     * Variables already defined in the environment are kept unless `$overwrite` is true.
     * Values are exposed through getenv(), $_ENV and $_SERVER.
     *
     * @param  string                    $path      Path of the .env file
     * @param  bool                      $overwrite Replace variables already defined
     * @return array                     Variables defined by the file
     * @throws \InvalidArgumentException If the file contains an invalid line
     * @throws \RuntimeException         If the file is not readable
     */
    public function loadEnv($path, $overwrite = false)
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('Environment file not readable: '.$path);
        }

        $lines = preg_split('/\r\n|\r|\n/', file_get_contents($path));
        $count = count($lines);
        $defined = [];

        for ($i = 0; $i < $count; ++$i) {
            $number = $i + 1;
            $line = trim($lines[$i]);

            if ('' === $line || '#' === $line[0]) {
                continue;
            }

            if (!preg_match('/^(?:export\s+)?([A-Za-z_][A-Za-z0-9_.]*)\s*=\s*(.*)$/', $line, $match)) {
                throw new \InvalidArgumentException(sprintf('Invalid line %d in %s', $number, $path));
            }

            $name = $match[1];
            $raw = $match[2];
            $quote = '' !== $raw && ('"' === $raw[0] || "'" === $raw[0]) ? $raw[0] : null;

            if (null === $quote) {
                // inline comments require a leading whitespace, so "a#b" is a valid value
                $value = $this->interpolate(trim(preg_replace('/\s+#.*$/', '', $raw)), $defined);
            } else {
                $pattern = '"' === $quote
                    ? '/^"((?:[^"\\\\]|\\\\.)*)"\s*(?:#.*)?$/s'
                    : "/^'([^']*)'\\s*(?:#.*)?$/s";

                // quoted values can span multiple lines until the closing quote
                while (!preg_match($pattern, $raw, $quoted)) {
                    if (++$i >= $count) {
                        throw new \InvalidArgumentException(
                            sprintf('Unclosed quote at line %d in %s', $number, $path)
                        );
                    }

                    $raw .= "\n".rtrim($lines[$i]);
                }

                $value = '"' === $quote
                    ? $this->interpolate($quoted[1], $defined, true)
                    : $quoted[1];
            }

            if (!$overwrite && false !== $this->lookup($name, [])) {
                $defined[$name] = $this->lookup($name, []);

                continue;
            }

            $defined[$name] = $value;

            putenv($name.'='.$value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }

        return $defined;
    }

    /**
     * Retrieve an environment variable.
     *
     * The strings "true", "false", "null" and "empty" (case insensitive,
     * optionally wrapped in parentheses) are converted to true, false, null and "".
     *
     * @param  string $name    Variable name
     * @param  mixed  $default Value returned when the variable is not defined
     * @return mixed
     */
    public function env($name, $default = null)
    {
        $value = $this->lookup($name, []);

        if (false === $value) {
            return $default;
        }

        switch (strtolower(trim($value, '()'))) {
            case 'true':
                return true;

            case 'false':
                return false;

            case 'null':
                return null;

            case 'empty':
                return '';
        }

        return $value;
    }

    /**
     * Find a variable among the given ones and the environment.
     *
     * @param  string       $name    Variable name
     * @param  array        $defined Variables defined by the file being loaded
     * @return string|false
     */
    private function lookup($name, array $defined)
    {
        if (array_key_exists($name, $defined)) {
            return $defined[$name];
        }

        // $_ENV is empty when variables_order doesn't contain "E"
        if (isset($_ENV[$name]) && is_string($_ENV[$name])) {
            return $_ENV[$name];
        }

        if (isset($_SERVER[$name]) && is_string($_SERVER[$name])) {
            return $_SERVER[$name];
        }

        return getenv($name);
    }

    /**
     * Replace ${NAME} references and, for double quoted values, escape sequences.
     *
     * @param  string $value   Raw value
     * @param  array  $defined Variables defined by the file being loaded
     * @param  bool   $escapes Whether escape sequences have to be converted
     * @return string
     */
    private function interpolate($value, array $defined, $escapes = false)
    {
        $pattern = $escapes
            ? '/\\\\(.)|\$\{([A-Za-z_][A-Za-z0-9_.]*)\}/s'
            : '/()\$\{([A-Za-z_][A-Za-z0-9_.]*)\}/';

        return preg_replace_callback($pattern, function ($match) use ($defined) {
            if (isset($match[2]) && '' !== $match[2]) {
                $found = $this->lookup($match[2], $defined);

                return false === $found ? '' : $found;
            }

            $map = ['n' => "\n", 'r' => "\r", 't' => "\t"];

            // unknown sequences (e.g. \" \\ \$) are replaced by the escaped character
            return $map[$match[1]] ?? $match[1];
        }, $value);
    }

    /**
     * Parse a configuration file.
     *
     * @param  string                    $path File path
     * @return array
     * @throws \InvalidArgumentException If the file type is unsupported or doesn't contain an array
     * @throws \RuntimeException         If the file is not readable or can't be parsed
     */
    private function read($path)
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('Configuration file not readable: '.$path);
        }

        switch (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            case 'php':
                $values = (static function () use ($path) {
                    return require $path;
                })();

                break;

            case 'json':
                $values = json_decode(file_get_contents($path), true);

                if (JSON_ERROR_NONE !== json_last_error()) {
                    throw new \RuntimeException(
                        sprintf('Invalid JSON in %s: %s', $path, json_last_error_msg())
                    );
                }

                break;

            case 'ini':
                $values = @parse_ini_file($path, true, INI_SCANNER_TYPED);

                if (false === $values) {
                    throw new \RuntimeException('Invalid INI file: '.$path);
                }

                break;

            default:
                throw new \InvalidArgumentException('Unsupported configuration file: '.$path);
        }

        if (!is_array($values)) {
            throw new \InvalidArgumentException('Configuration file must contain an array: '.$path);
        }

        return $values;
    }

    /**
     * Merge configuration arrays recursively.
     *
     * @param  array $base     Current values
     * @param  array $override New values
     * @return array
     */
    private function merge(array $base, array $override)
    {
        foreach ($override as $key => $value) {
            if (
                is_array($value) && !array_is_list($value) &&
                isset($base[$key]) && is_array($base[$key]) && !array_is_list($base[$key])
            ) {
                $value = $this->merge($base[$key], $value);
            }

            $base[$key] = $value;
        }

        return $base;
    }
};
