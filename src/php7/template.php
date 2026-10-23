<?php

unset($deps['template:escape']);

$deps['template'] = new class () {
    /**
     * Layout requested by the template currently rendered.
     *
     * @var array|null
     */
    private $layout;

    /**
     * Rendered sections, shared between a template and its layouts.
     *
     * @var array
     */
    private $sections = [];

    /**
     * Names of the sections opened and not closed yet.
     *
     * @var array
     */
    private $sectionsStack = [];

    /**
     * Nesting level of render calls, used to reset sections between renders.
     *
     * @var int
     */
    private $depth = 0;

    /**
     * Simple template engine to manipulate and render .php files.
     *
     * @return string
     */
    public function render(string $template, array $data = [])
    {
        if (false === stream_resolve_include_path($template)) {
            throw new InvalidArgumentException(sprintf('Template "%s" not found.', $template));
        }

        if (0 === $this->depth) {
            $this->sections = [];
        }

        $obLevel = ob_get_level();
        $stackSize = count($this->sectionsStack);
        $previousLayout = $this->layout;
        $this->layout = null;
        ++$this->depth;

        try {
            ob_start();

            // the closure isolates the scope, $template and $data can't be overwritten
            $load = function () {
                extract(func_get_arg(1), EXTR_SKIP);

                require func_get_arg(0);
            };
            $load($template, array_diff_key($data, ['this' => null]));

            if (count($this->sectionsStack) > $stackSize) {
                throw new LogicException(sprintf('Section "%s" not closed.', end($this->sectionsStack)));
            }

            $content = ob_get_clean();
            $layout = $this->layout;

            if (null !== $layout) {
                $this->sections['content'] = $content;
                $content = $this->render($layout[0], array_merge($data, $layout[1]));
            }

            return $content;
        } finally {
            // buffers left open by a failure (or by unclosed sections) are discarded
            while (ob_get_level() > $obLevel) {
                ob_end_clean();
            }

            $this->sectionsStack = array_slice($this->sectionsStack, 0, $stackSize);
            $this->layout = $previousLayout;
            --$this->depth;

            if (0 === $this->depth) {
                $this->sections = [];
            }
        }
    }

    /**
     * Wrap the current template into a layout, the output of the template
     * will be available in the layout as the "content" section.
     *
     * @param string $template
     * @param array  $data     additional data merged into the template data
     */
    public function layout(string $template, array $data = [])
    {
        $this->layout = [$template, $data];
    }

    /**
     * Start capturing the output of a section.
     *
     * @param string $name
     */
    public function start(string $name)
    {
        if ('content' === $name) {
            throw new LogicException('Section name "content" is reserved.');
        }

        $this->sectionsStack[] = $name;

        ob_start();
    }

    /**
     * Stop capturing the output of the last section started.
     * Sections defined by a template have precedence over the ones defined
     * by its layouts, so layouts can define defaults.
     */
    public function stop()
    {
        if (empty($this->sectionsStack)) {
            throw new LogicException('No section started.');
        }

        $name = array_pop($this->sectionsStack);
        $content = ob_get_clean();

        if (!isset($this->sections[$name])) {
            $this->sections[$name] = $content;
        }
    }

    /**
     * Get the content of a section.
     *
     * @param string $name
     * @param string $default returned when the section isn't defined
     *
     * @return string
     */
    public function section(string $name, string $default = '')
    {
        return $this->sections[$name] ?? $default;
    }

    /**
     * Escape values to be rendered safely in templates.
     *
     * @param mixed $value
     *
     * @return string
     */
    public function e($value)
    {
        if (is_array($value) || (is_object($value) && !method_exists($value, '__toString'))) {
            throw new InvalidArgumentException(sprintf('Value of type "%s" can\'t be escaped.', gettype($value)));
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
};
