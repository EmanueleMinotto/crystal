<?php

$deps['template:escape'] = function () {
    return function ($value) {
        return \htmlspecialchars($value);
    };
};
