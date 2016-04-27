<?php
/**
 * Autoloading based on the PSR-0 standard and extended predefined configuration
 * https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md
 */
spl_autoload_register(function ($class) {
    // Use predefined paths
    $paths = explode(PATH_SEPARATOR, get_include_path());

    // fix for UNIX
    if (PATH_SEPARATOR === ':') {
        foreach ($paths as $key => $value) {
            if (substr(strtolower($value), 0, 4) === 'http' && isset($paths[$key + 1])) {
                $paths[$key] .= ':' . $paths[$key + 1];
                unset($paths[$key + 1]);
            }
        }
    }
    // remove duplicated paths
    $paths = array_values(array_unique($paths));

    // Realpaths and URLs
    array_walk($paths, function (&$path) {
        $path = filter_var($path, FILTER_VALIDATE_URL)
            ? rtrim($path, '/') . '/'
            : rtrim(realpath($path), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    });
    array_filter($paths, function ($path) {
        return !is_null(trim($path, '/' . DIRECTORY_SEPARATOR));
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
            $uri = filter_var($path, FILTER_VALIDATE_URL)
                ? $path . implode('/', $tokens) . $extension
                : $path . implode(DIRECTORY_SEPARATOR, $tokens) . $extension;

            if (filter_var($uri, FILTER_VALIDATE_URL)) {
                if (ini_get('allow_url_fopen') && ini_get('allow_url_include')) {
                    // http://php.net/manual/en/filesystem.configuration.php#ini.allow-url-include

                    // spl_autoload_register returns bool
                    // http://it2.php.net/manual/en/function.spl-autoload-register.php
                    return ((require_once $uri) === 1);
                } elseif (extension_loaded('curl')) {
                    // cURL is a preferred mode
                    $ch = curl_init($uri);

                    curl_setopt_array($ch, array(
                        CURLOPT_HEADER => 0,
                        CURLOPT_RETURNTRANSFER => 1,
                        CURLOPT_BINARYTRANSFER => 1,
                        CURLOPT_FOLLOWLOCATION => 1,
                        CURLOPT_FRESH_CONNECT => 1,
                        CURLOPT_SSL_VERIFYHOST => 0,
                        CURLOPT_SSL_VERIFYPEER => 0,
                        CURLOPT_FAILONERROR => 1
                    ));

                    $content = curl_exec($ch);

                    // Loading only right pages
                    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
                        continue;
                    }

                    curl_close($ch);

                    // if the time it's not a problem the file will be saved for a faster access
                    // file will be written in first accessible directory in `$paths`
                    if (!ini_get('max_execution_time')) {
                        foreach ($paths as $path) {
                            if (filter_var($path, FILTER_VALIDATE_URL)) {
                                continue;
                            }

                            $file = $path . implode(DIRECTORY_SEPARATOR, $tokens) . $extension;
                            if (is_writable($path) && (!file_exists($file) || is_writable($file))) {
                                // create the directory
                                if (!file_exists(dirname($file))) {
                                    for ($i = 0; $i < count($tokens); $i++) {
                                        $dir = $path . implode(DIRECTORY_SEPARATOR, array_slice($tokens, 0, $i));
                                        if (!file_exists($dir)) {
                                            mkdir($dir);
                                        }
                                    }
                                }

                                $handle = fopen($file, 'w+');
                                fwrite($handle, $content);
                                fclose($handle);

                                return ((require_once $file) === 1);
                            }
                        }
                    } elseif (is_writable(sys_get_temp_dir())) {
                        $tmp_file = tempnam(sys_get_temp_dir(), '__autoload');

                        $handle = fopen($tmp_file, 'w+');
                        fwrite($handle, $content);
                        fclose($handle);

                        $return = ((require_once $tmp_file) === 1);

                        unlink($tmp_file);

                        // spl_autoload_register returns bool
                        // http://it2.php.net/manual/en/function.spl-autoload-register.php
                        return $return;
                    }
                }
            } elseif (file_exists($uri)) {
                // spl_autoload_register returns bool
                // http://it2.php.net/manual/en/function.spl-autoload-register.php
                return ((require_once $uri) === 1);
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
     * @var null|callable
     */
    static $deploy = null;
    /**
     * Defined matches.
     * @var array
     */
    static $ms = array();
    /**
     * Dependency Injection callbacks, used for settings too.
     * @var null|array
     */
    static $di = null;
    // there's already a container for variables
    if (is_null($di)) {
        $di =& $GLOBALS;
    }
    /**
     * This variable is a constant during an instance.
     * @var null|string
     */
    static $base = null;
    // base path for each route defined once
    if (is_null($base)) {
        $base = quotemeta(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'));
    }

    // used to shorten code
    $num_args = func_num_args();
    $get_args = func_get_args();

    // used to retrieve currently defined matches
    // http://www.php.net/manual/en/regexp.reference.conditional.php
    // http://stackoverflow.com/questions/14598972/catch-all-regular-expression
    switch ($num_args) {
        case 0: {
            if (PHP_SAPI !== 'cli') {
                return '/?(?!(' . implode('|', $ms) . ')$).*';
            }
            break;
        }
        case 1: {
            if (is_scalar($get_args[0])) {
                // using $GLOBALS as a container, variable names must match
                // this regular expression
                // http://www.php.net/manual/en/language.variables.basics.php
                if (preg_match('#^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*#', $get_args[0])) {
                    return is_callable($di[$get_args[0]])
                        ? call_user_func($di[$get_args[0]])
                        : $di[$get_args[0]];
                }
            }
            break;
        }
        case 2: {
            if (is_scalar($get_args[0])) {
                // using $GLOBALS as a container, variable names must match
                // this regular expression
                // http://www.php.net/manual/en/language.variables.basics.php
                if (preg_match('#^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*#', $get_args[0])) {
                    // functions used for Dependency Injection and settings
                    return $di[$get_args[0]] = $get_args[1];
                }
            }
            break;
        }
    }

    // functions have to be stored only once
    if (is_null($deploy) && PHP_SAPI === 'cli') {
        /**
         * Command line interface for the main function.
         *
         * @link http://php.net/manual/en/language.types.float.php
         *
         * @param  callback $cb       Function invoked when script ends
         * @param  integer  $priority Set `$cb` priority from 0 (high) to ~1.8e308 (low)
         * @return void
         */
        $deploy = function ($cb, $priority = 0) use (&$deploy) {
            // Checking well formed call
            assert(is_callable($cb));
            assert(is_numeric($priority));

            /**
             * Arguments passed to the script.
             * @link http://php.net/manual/en/reserved.variables.argv.php
             * @var array
             */
            $argv = $GLOBALS['argv'];

            if ($priority > 0) {
                // Recursion is used to set callback priority
                register_shutdown_function($deploy, $cb, $priority - 1);
            } else {
                $argv[0] = $cb;
                // register_shutdown_function is used to call added functions when script ends
                // http://it2.php.net/manual/en/function.register-shutdown-function.php
                call_user_func_array('register_shutdown_function', $argv);
            }
        };
    } elseif (is_null($deploy)) {
        /**
         * Function used as a router.
         *
         * @link http://php.net/manual/en/language.types.float.php
         *
         * @param  string   $regex    Regular expression used to match requested URL
         * @param  callback $cb       Function invoked when there's a match
         * @param  string   $method   Request method(s)
         * @param  float    $priority Set `$cb` priority from 0 (high) to ~1.8e308 (low)
         * @return void
         */
        $deploy = function ($regex, $cb, $method = 'GET', $priority = 0) use (&$deploy, $ms, $base) {
            // Checking well formed call
            assert(is_string($regex));
            assert(is_callable($cb));
            assert(is_string($method));
            assert(is_numeric($priority));

            // match stored as unique using the Adler-32 algorithm that is faster than md5
            // http://en.wikipedia.org/wiki/Adler-32
            // http://3v4l.org/7MC3j
            $ms[hash('adler32', $regex)] = $regex;

            if ($priority > 0) {
                // Recursion is used to set callback priority
                register_shutdown_function($deploy, $regex, $cb, $method, $priority - 1);
            } elseif (preg_match('#' . $method . '#', $_SERVER['REQUEST_METHOD'])) {
                if (preg_match('#^' . $base . $regex . '$#', $_SERVER['REQUEST_URI'], $matches)) {
                    // Named subpatterns are allowed
                    // http://it2.php.net/manual/en/regexp.reference.subpatterns.php
                    $matches = array_unique($matches);
                    // If matches is provided, then it is filled with the results of search.
                    // $matches[0] will contain the text that matched the full pattern,
                    // $matches[1] will have the text that matched the first captured parenthesized
                    // subpattern, and so on.
                    $start_match = $matches[0];
                    unset($matches[0]);

                    // Snippet used to extract parameter from a callable object.
                    $Reflector = (is_string($cb) && function_exists($cb)) || $cb instanceof Closure
                        ? new ReflectionFunction($cb)
                        : new ReflectionMethod($cb);
                    $params = array();
                    foreach ($Reflector->getParameters() as $parameter) {
                        // reset to prevent key value
                        $params[$parameter->name] = null;
                    }
                    // user can use named parameters only if explicitly requested
                    if (array_intersect(array_keys($params), array_keys($matches))) {
                        $matches = array_merge($params, $matches);
                    }
                    array_unshift($matches, $cb);

                    // register_shutdown_function is used to call added functions when script ends
                    // http://it2.php.net/manual/en/function.register-shutdown-function.php
                    call_user_func_array('register_shutdown_function', $matches);
                }
            }
        };
    }

    return call_user_func_array($deploy, func_get_args());
};
