<?php

$deps['template:escape'] = function () {
    /**
     * Escape values to be rendered safely in templates.
     *
     * @param string $value
     *
     * @return string
     */
    return function ($value) {
        $flags = defined('ENT_SUBSTITUTE')
            ? ENT_QUOTES | ENT_SUBSTITUTE
            : ENT_QUOTES;

        return htmlspecialchars($value, $flags, 'UTF-8');
    };
};
