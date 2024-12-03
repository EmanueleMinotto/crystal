<?php

if (!function_exists('array_is_list')) {
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
