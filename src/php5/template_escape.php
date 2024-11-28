<?php

$deps['template:escape'] = function () {
    return function ($value) {
        $flags = defined('ENT_SUBSTITUTE')
            ? ENT_QUOTES | ENT_SUBSTITUTE
            : ENT_QUOTES;

        return \htmlspecialchars($value, $flags, 'UTF-8');
    };
};
