<?php

$deps['template'] = function () {
    /**
     * Simple template engine to manipulate and render .php files.
     *
     * @param string $filename
     * @param array $data
     *
     * @return string
     */
    return function ($filename, $data = array()) {
        assert(file_exists($filename));
        assert(is_array($data));

        ob_start();

        extract($data);

        require $filename;

        return ob_get_clean();
    };
};
