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
     * Generic logger using `syslog` for tracking, use `openlog` to change its behaviour.
     *
     * @link https://www.php.net/manual/en/function.syslog.php
     *
     * @param string $level
     * @param string $message
     * @param array $context
     */
    return function ($level, $message, $context = array()) use ($levels) {
        $level = strtolower($level);

        assert(array_key_exists($level, $levels));
        assert(is_string($message));
        assert(is_array($context));

        syslog($levels[$level], $message.' '.json_encode($context));
    };
};
