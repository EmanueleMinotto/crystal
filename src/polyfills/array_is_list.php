<?php

/**
 * Polyfill for array_is_list() function (PHP < 8.1).
 *
 * Checks if an array's keys are consecutive numbers starting from 0.
 * Returns true for empty arrays and arrays with sequential integer keys.
 *
 * @see https://www.php.net/manual/en/function.array-is-list.php
 */
if (!function_exists('array_is_list')) {
    /**
     * Determines if the given array is a list (sequential integer keys starting from 0).
     *
     * @param  array $array Array to check
     * @return bool  True if array is a list, false otherwise
     */
    function array_is_list($array)
    {
        if (array() === $array || $array === array_values($array)) {
            return true;
        }

        $nextKey = -1;

        foreach ($array as $k => $v) {
            if ($k !== ++$nextKey) {
                return false;
            }
        }

        return true;
    }
}
