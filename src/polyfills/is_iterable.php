<?php

/**
 * Polyfill for is_iterable() function (PHP < 7.1).
 *
 * Provides the is_iterable() function if it does not exist, allowing code to check
 * if a variable is iterable (array or Traversable object).
 *
 * @see https://www.php.net/manual/en/function.is-iterable.php
 */

if (!function_exists('is_iterable')) {
    /**
     * Checks if the given variable is iterable (array or Traversable).
     *
     * @param  mixed $obj Variable to check
     * @return bool  True if iterable, false otherwise
     */
    function is_iterable($obj)
    {
        return is_array($obj) || (is_object($obj) && ($obj instanceof \Traversable));
    }
}
