<?php

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
