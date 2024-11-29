<?php

unset($deps['template:escape']);

$deps['template'] = new class () {
    /**
     * Simple template engine to manipulate and render .php files.
     *
     * @return string
     */
    public function render(string $template, array $data = array())
    {
        ob_start();

        extract($data);

        require $template;

        return ob_get_clean();
    }

    /**
     * Escape values to be rendered safely in templates.
     *
     * @param string $value
     *
     * @return string
     */
    public function e($value)
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
};
