<?php

/**
 * Polyfill for the JsonSerializable interface (PHP < 5.4).
 *
 * Provides the JsonSerializable interface if it does not exist, allowing objects
 * to define custom JSON serialization logic via the jsonSerialize() method.
 *
 * @see https://www.php.net/manual/en/class.jsonserializable.php
 */
if (!interface_exists('JsonSerializable')) {
    /**
     * Interface for classes that can be serialized to JSON.
     *
     * Implement this interface to define how your object should be converted to JSON.
     */
    interface JsonSerializable
    {
        /**
         * Returns data that can be serialized by json_encode().
         *
         * @return mixed Data for JSON serialization
         */
        public function jsonSerialize();
    }
}
