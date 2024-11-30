<?php

// logger based on PSR-3
// https://www.php-fig.org/psr/psr-3/
$deps['logger'] = new class () {
    /**
     * Possible level values.
     */
    private $logLevels = array(
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
     * Custom logger implementation, if defined.
     *
     * @var callable|null
     */
    private $implementation;

    /**
     * Additional context for every message.
     *
     * @var array
     */
    private $context = array();

    private function interpolate($message, array $context = array())
    {
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
    }

    /**
     * Add shared context value.
     *
     * @param string $key
     * @param mixed $value
     */
    public function addContext(string $key, $value)
    {
        $this->context[$key] = $value;
    }

    /**
     * Set all the shared context.
     *
     * @param array $context
     */
    public function setContext(array $context)
    {
        $this->context = $context;
    }

    /**
     * Get all the shared context.
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Unset all the values in the shared context.
     */
    public function resetContext()
    {
        $this->context = array();
    }

    /**
     * Remove specific keys from the shared context.
     *
     * @param string $keys
     */
    public function unsetContext(string ...$keys)
    {
        foreach ($keys as $key) {
            unset($this->context[$key]);
        }
    }

    /**
     * System is unusable.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function emergency($message, $context = array())
    {
        $this->log('emergency', $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function alert($message, $context = array())
    {
        $this->log('alert', $message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function critical($message, $context = array())
    {
        $this->log('critical', $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function error($message, $context = array())
    {
        $this->log('error', $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function warning($message, $context = array())
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function notice($message, $context = array())
    {
        $this->log('notice', $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function info($message, $context = array())
    {
        $this->log('info', $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function debug($message, $context = array())
    {
        $this->log('debug', $message, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function log($level, $message, $context = array())
    {
        $context = array_merge($this->context, $context);
        $message = $this->interpolate($message, $context);

        if (!empty($this->implementation)) {
            return call_user_func_array($this->implementation, array(
                $level,
                $message,
                $context
            ));
        }

        syslog(
            $this->logLevels[mb_strtolower($level)] ?? $level,
            $message.' '.json_encode($context)
        );
    }

    /**
     * Sets a custom implementation instead of the default syslog.
     *
     * @param callable $implementation
     */
    public function setImplementation(callable $implementation)
    {
        $this->implementation = $implementation;
    }

    /**
     * Resets the implementation to the default one (using syslog).
     */
    public function resetImplementation()
    {
        $this->implementation = null;
    }
};
