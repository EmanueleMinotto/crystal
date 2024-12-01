<?php

if (!interface_exists('JsonSerializable')) {
    /**
     * @link https://www.php.net/manual/en/class.jsonserializable.php
     */
    interface JsonSerializable
    {
        public function jsonSerialize();
    }
}
