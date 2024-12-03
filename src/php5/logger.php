<?php

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
     * Interpolates context values into the message placeholders.
     */
    $interpolate = function ($message, $context = array()) {
        // build a replacement array with braces around the context keys
        $replace = array();

        foreach ($context as $key => $val) {
            // check that the value can be cast to string
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }

        // interpolate replacement values into the message and return
        return strtr($message, $replace);
    };

    /**
     * Generic logger using `syslog` for tracking, use `openlog` to change its behaviour.
     *
     * @link https://www.php.net/manual/en/function.syslog.php
     *
     * @param string $level
     * @param string $message
     * @param array  $context
     */
    return function ($level, $message, $context = array()) use ($interpolate, $levels) {
        $level = mb_strtolower($level);

        assert(array_key_exists($level, $levels));
        assert(is_string($message));
        assert(is_array($context));

        syslog($levels[$level], $interpolate($message).' '.json_encode($context));
    };
};
