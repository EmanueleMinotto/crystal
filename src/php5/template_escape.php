<?php

$deps['template:escape'] = function () {
    return function ($value) {
        return \htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };
};
