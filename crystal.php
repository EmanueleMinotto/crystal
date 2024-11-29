<?php

/**
 * Autoloading based on the PSR-0 standard and extended predefined configuration
 * https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md
 */
spl_autoload_register(function ($class) {
    // Use predefined paths
    $paths = explode(PATH_SEPARATOR, get_include_path());

    // remove duplicated paths
    $paths = array_values(array_unique($paths));

    // Realpaths and URLs
    array_walk($paths, function (&$path) {
        $path = rtrim(realpath($path), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
    });
    array_filter($paths, function ($path) {
        return !is_null(trim($path, '/'.DIRECTORY_SEPARATOR));
    });

    // Use predefined extensions
    $extensions = explode(',', spl_autoload_extensions());

    // Remove initial backslash `\`
    $class = ltrim($class, chr(92));
    // Explode by `\`
    $tokens = explode(chr(92), $class);
    // Each `_` character in the CLASS NAME is converted to a `DIRECTORY_SEPARATOR`.
    // The `_` character has no special meaning in the namespace.
    $class_tokens = explode('_', end($tokens));
    array_pop($tokens);

    array_walk($class_tokens, function (&$class_token) use (&$tokens) {
        $tokens[] = $class_token;
    });

    // Check if file exists and require(_once) it
    foreach ($paths as $path) {
        foreach ($extensions as $extension) {
            $uri = $path.implode(DIRECTORY_SEPARATOR, $tokens).$extension;

            if (file_exists($uri)) {
                // spl_autoload_register returns bool
                // http://it2.php.net/manual/en/function.spl-autoload-register.php
                return (require_once $uri) === 1;
            }
        }
    }

    // spl_autoload_register returns bool
    // http://it2.php.net/manual/en/function.spl-autoload-register.php
    return false;
});

/**
 * A PHP (5.3+) microframework based on anonymous functions.
 */
return function () {
    /**
     * Used to store functions and allow recursive callbacks.
     *
     * @var null|callable
     */
    static $deploy;

    /**
     * Defined matches.
     *
     * @var array
     */
    static $matches = array();

    /**
     * Dependency Injection callbacks, used for settings too.
     *
     * @var array
     */
    static $deps = array();

    /**
     * This variable is a constant during an instance.
     *
     * @var null|string
     */
    static $base;

    // base path for each route defined once
    if (is_null($base)) {
        $base = quotemeta(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'));
    }

    $deps['logger'] = function () {
        $levels = array(
            'emerg' => LOG_EMERG,
            'emergency' => LOG_EMERG,
            'alert' => LOG_ALERT,
            'crit' => LOG_CRIT,
            'critical' => LOG_CRIT,
            'err' => LOG_ERR,
            'error' => LOG_ERR,
            'warn' => LOG_WARNING,
            'warning' => LOG_WARNING,
            'notice' => LOG_NOTICE,
            'info' => LOG_INFO,
            'debug' => LOG_DEBUG,
        );

        /**
         * Generic logger using `syslog` for tracking, use `openlog` to change its behaviour.
         *
         * @link https://www.php.net/manual/en/function.syslog.php
         *
         * @param string $level
         * @param string $message
         * @param array $context
         */
        return function ($level, $message, $context = array()) use ($levels) {
            $level = mb_strtolower($level);

            assert(array_key_exists($level, $levels));
            assert(is_string($message));
            assert(is_array($context));

            syslog($levels[$level], $message.' '.json_encode($context));
        };
    };

    $deps['template'] = function () {
        /**
         * Simple template engine to manipulate and render .php files.
         *
         * @param string $filename
         * @param array $data
         *
         * @return string
         */
        return function ($filename, $data = array()) {
            assert(file_exists($filename));
            assert(is_array($data));

            ob_start();

            extract($data);

            require $filename;

            return ob_get_clean();
        };
    };

    $deps['template:escape'] = function () {
        return function ($value) {
            $flags = defined('ENT_SUBSTITUTE')
                ? ENT_QUOTES | ENT_SUBSTITUTE
                : ENT_QUOTES;

            return \htmlspecialchars($value, $flags, 'UTF-8');
        };
    };

    /**
     * Double access utility.
     *
     * @link https://github.com/EmanueleMinotto/crystal/wiki/Double-access-utility
     *
     * @var Closure
     */
    $deps['utils:double-access'] = function () {
        // real function
        return function ($data = array()) use (&$fn) {
            // if there's something that isn't an
            // array, it'll not be converted
            if (!is_array($data)) {
                return;
            }

            foreach ($data as $key => $value) {
                // only arrays can be transformed in ArrayObjects
                // the 2nd condition is used to prevent recursion
                // other cases are for objects obviously of
                // another type that will be converted
                if (is_array($value) && $value !== $data) {
                    $data[$key] = $fn($value);
                } elseif ($value === (string) intval($value)) {
                    $data[$key] = intval($value);
                } elseif ($value === (string) floatval($value)) {
                    $data[$key] = floatval($value);
                }
            }

            return new ArrayObject($data, 2);
        };
    };

    // hack used to include PHP 7 enhancements and features
    // without breaking changes nor new files
    // https://3v4l.org/mArem
    if (PHP_MAJOR_VERSION >= 7) {
        $handler = fopen(__FILE__, 'r');
        fseek($handler, __COMPILER_HALT_OFFSET__);
        eval(stream_get_contents($handler));
        fclose($handler);
    }

    // used to shorten code
    $args = func_get_args();

    // used to retrieve currently defined matches
    // http://www.php.net/manual/en/regexp.reference.conditional.php
    // http://stackoverflow.com/questions/14598972/catch-all-regular-expression
    switch (func_num_args()) {
        case 1:
            // Set of utilities
            switch ($args[0]) {
                case 'get':
                case 'post':
                case 'cookie':
                case 'env':
                case 'request':
                case 'server':
                    return $deps['utils:double-access']($GLOBALS['_'.mb_strtoupper($args[0])]);

                case 'router:not-found':
                    if (!empty($_SERVER['REQUEST_URI'])) {
                        return '(?!('.implode('|', $matches).')$).*';
                    }

                    break;
            }

            // using $GLOBALS as a container, variable names must match
            // this regular expression
            // http://www.php.net/manual/en/language.variables.basics.php
            if (is_scalar($args[0]) && preg_match('#^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*#', $args[0])) {
                return is_callable($deps[$args[0]])
                    ? call_user_func($deps[$args[0]])
                    : $deps[$args[0]];
            }

            break;

        case 2:
            // using $GLOBALS as a container, variable names must match
            // this regular expression
            // http://www.php.net/manual/en/language.variables.basics.php
            if (is_scalar($args[0]) && preg_match('#^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*#', $args[0])) {
                // functions used for Dependency Injection and settings
                return $deps[$args[0]] = $args[1];
            }

            break;
    }

    if (!is_null($deploy)) {
        goto invoke_deploy;
    }

    // functions have to be stored only once
    if (empty($_SERVER['REQUEST_URI'])) {
        /**
         * Command line interface for the main function.
         *
         * @param callback $cb       Function invoked when script ends
         * @param int      $priority Set `$cb` priority from 0 (high) to ~1.8e308 (low)
         *
         * @link http://php.net/manual/en/language.types.float.php
         *
         * @return void
         */
        $deploy = function ($cb, $priority = 0) use (&$deploy) {
            // Checking well formed call
            assert(is_callable($cb));
            assert(is_numeric($priority));

            if ($priority > 0) {
                // Recursion is used to set callback priority
                return register_shutdown_function($deploy, $cb, $priority - 1);
            }

            /**
             * Arguments passed to the script.
             *
             * @link http://php.net/manual/en/reserved.variables.argv.php
             *
             * @var array
             */
            $argv = $GLOBALS['argv'];

            $argv[0] = $cb;

            // register_shutdown_function is used to call added functions when script ends
            // http://it2.php.net/manual/en/function.register-shutdown-function.php
            return call_user_func_array('register_shutdown_function', array_values($argv));
        };

        goto invoke_deploy;
    }

    /**
     * Function used as a router.
     *
     * @param string   $regex    Regular expression used to match requested URL
     * @param callback $cb       Function invoked when there's a match
     * @param string   $method   Request method(s)
     * @param float    $priority Set `$cb` priority from 0 (high) to ~1.8e308 (low)
     *
     * @link http://php.net/manual/en/language.types.float.php
     *
     * @return void
     */
    $deploy = function ($regex, $cb, $method = 'GET', $priority = 0) use (&$deploy, &$matches, $base) {
        // Checking well formed call
        assert(is_string($regex));
        assert(is_callable($cb));
        assert(is_string($method));
        assert(is_numeric($priority));

        // match stored as unique using the Adler-32 algorithm that is faster than md5
        // http://en.wikipedia.org/wiki/Adler-32
        // http://3v4l.org/7MC3j
        $matches[hash('adler32', $regex)] = $regex;

        if ($priority > 0) {
            // Recursion is used to set callback priority
            return register_shutdown_function($deploy, $regex, $cb, $method, $priority - 1);
        }

        if (
            preg_match('#'.$method.'#', $_SERVER['REQUEST_METHOD']) &&
            preg_match('#^'.$base.$regex.'$#', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), $submatches)
        ) {
            // Named subpatterns are allowed
            // http://it2.php.net/manual/en/regexp.reference.subpatterns.php
            $submatches = array_unique($submatches);
            // If matches is provided, then it is filled with the results of search.
            // $submatches[0] will contain the text that matched the full pattern,
            // $submatches[1] will have the text that matched the first captured parenthesized
            // subpattern, and so on.
            unset($submatches[0]);

            // Snippet used to extract parameter from a callable object.
            $reflector = (is_string($cb) && function_exists($cb)) || $cb instanceof Closure
                ? new ReflectionFunction($cb)
                : new ReflectionMethod($cb);
            $params = array();

            foreach ($reflector->getParameters() as $parameter) {
                // reset to prevent key value
                $params[$parameter->name] = null;
            }

            // user can use named parameters only if explicitly requested
            if (array_intersect(array_keys($params), array_keys($submatches))) {
                $submatches = array_merge($params, $submatches);
            }
            array_unshift($submatches, $cb);

            // register_shutdown_function is used to call added functions when script ends
            // http://it2.php.net/manual/en/function.register-shutdown-function.php
            return call_user_func_array('register_shutdown_function', array_values($submatches));
        }
    };

    // invoking deploy
    invoke_deploy:
    return call_user_func_array($deploy, func_get_args());
};

__halt_compiler();

// PHP 7 features, use `$deps` for dependency injection

// logger based on PSR-3
// https://www.php-fig.org/psr/psr-3/
$deps['logger'] = new class () {
    private $logLevels = array(
        'emerg' => LOG_EMERG,
        'emergency' => LOG_EMERG,
        'alert' => LOG_ALERT,
        'crit' => LOG_CRIT,
        'critical' => LOG_CRIT,
        'err' => LOG_ERR,
        'error' => LOG_ERR,
        'warn' => LOG_WARNING,
        'warning' => LOG_WARNING,
        'notice' => LOG_NOTICE,
        'info' => LOG_INFO,
        'debug' => LOG_DEBUG,
    );

    /**
     * System is unusable.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function emergency($message, $context = array())
    {
        $this->log('emergency', $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function alert($message, $context = array())
    {
        $this->log('alert', $message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function critical($message, $context = array())
    {
        $this->log('critical', $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function error($message, $context = array())
    {
        $this->log('error', $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function warning($message, $context = array())
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function notice($message, $context = array())
    {
        $this->log('notice', $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function info($message, $context = array())
    {
        $this->log('info', $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function debug($message, $context = array())
    {
        $this->log('debug', $message, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     * @return void
     */
    public function log($level, $message, $context = array())
    {
        syslog(
            $this->logLevels[mb_strtolower($level)] ?? $level,
            $message.' '.json_encode($context)
        );
    }
};

