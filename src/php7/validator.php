<?php

/**
 * Validation of input data.
 *
 * Rules of each field are an array: rules without parameters are values,
 * rules with parameters are keys, custom rules are callables:
 *
 *     [
 *         'email' => ['required', 'email', 'max' => 255],
 *         'age' => ['integer', 'between' => [18, 120]],
 *         'code' => [function ($value, $field, $data) { return ctype_alnum($value) ?: 'must be alphanumeric'; }],
 *     ]
 *
 * Nested fields use dots and `*` for each item of an array, like `address.city`
 * and `items.*.quantity`, errors are listed with the path of each item (`items.0.quantity`).
 *
 * Empty fields (missing, null, empty strings or arrays) are valid unless required.
 */
$deps['validator'] = new class () {
    /**
     * Custom rules, name => [callable, message].
     *
     * @var array
     */
    private $rules = [];

    /**
     * Whether the validated values of `integer`, `numeric` and `boolean` fields are converted.
     *
     * @var bool
     */
    private $cast = false;

    /**
     * Default messages, :field is replaced by the field name, :param by the rule parameter.
     *
     * @var string[]
     */
    private $messages = [
        'required' => 'The :field field is required.',
        'string' => 'The :field field must be a string.',
        'integer' => 'The :field field must be an integer.',
        'numeric' => 'The :field field must be a number.',
        'boolean' => 'The :field field must be true or false.',
        'array' => 'The :field field must be an array.',
        'email' => 'The :field field must be a valid email address.',
        'url' => 'The :field field must be a valid URL.',
        'ip' => 'The :field field must be a valid IP address.',
        'date' => 'The :field field must be a valid date.',
        'min' => 'The :field field must be at least :param.',
        'max' => 'The :field field must not be greater than :param.',
        'between' => 'The :field field must be between :min and :max.',
        'in' => 'The selected :field is invalid.',
        'regex' => 'The :field field format is invalid.',
        'same' => 'The :field field must match :param.',
        'confirmed' => 'The :field field confirmation does not match.',
        'custom' => 'The :field field is invalid.',
    ];

    /**
     * Add a reusable rule.
     *
     * @param string   $name    Rule name
     * @param callable $check   Receives value, parameter, field name and data, returns true, false or a message
     * @param string   $message Default message
     */
    public function rule(string $name, callable $check, string $message = 'The :field field is invalid.')
    {
        $this->rules[$name] = [$check, $message];
    }

    /**
     * Convert the validated values of `integer`, `numeric` and `boolean` fields
     * to int, int or float and bool, like `"42"` to `42`.
     */
    public function castTypes(bool $cast = true)
    {
        $this->cast = $cast;
    }

    /**
     * Validate the data, returning the validated fields.
     *
     * @param array $data     Data to validate
     * @param array $rules    Rules of each field
     * @param array $messages Custom messages, keys are "field.rule" or "rule"
     *
     * @throws RuntimeException With status code 422 and the `getErrors` method, when the data isn't valid
     */
    public function validate(array $data, array $rules, array $messages = []): array
    {
        $errors = $this->check($data, $rules, $messages);

        if (!empty($errors)) {
            throw new class ($errors) extends RuntimeException {
                private $errors;

                public function __construct(array $errors)
                {
                    parent::__construct('The given data is invalid.');
                    $this->errors = $errors;
                }

                public function getStatusCode(): int
                {
                    return 422;
                }

                /**
                 * Error messages, field => messages.
                 */
                public function getErrors(): array
                {
                    return $this->errors;
                }
            };
        }

        $validated = array_intersect_key($data, $rules);

        foreach ($validated as $field => $value) {
            $validated[$field] = $this->cast($value, $rules[$field]);
        }

        foreach ($rules as $field => $fieldRules) {
            if (array_key_exists($field, $data)) {
                continue;
            }

            foreach ($this->expand((string) $field, $data) as $item) {
                list($path, $value, $exists) = $item;

                if (!$exists || 1 === count($path)) {
                    continue;
                }

                // nested values
                $target = &$validated;

                foreach ($path as $key) {
                    if (!isset($target[$key]) || !is_array($target[$key])) {
                        $target[$key] = [];
                    }

                    $target = &$target[$key];
                }

                $target = $this->cast($value, $fieldRules);
                unset($target);
            }
        }

        return $validated;
    }

    /**
     * Error messages of each invalid field, empty when the data is valid.
     *
     * @param array $data     Data to validate
     * @param array $rules    Rules of each field
     * @param array $messages Custom messages, keys are "field.rule" or "rule"
     *
     * @return array field => messages
     */
    public function check(array $data, array $rules, array $messages = []): array
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            if (!is_array($fieldRules)) {
                throw new InvalidArgumentException(sprintf('Rules of the "%s" field must be an array.', $field));
            }

            foreach ($this->expand((string) $field, $data) as $item) {
                $path = implode('.', $item[0]);
                $fieldErrors = $this->checkField($path, $item[1], $fieldRules, $data, $messages, (string) $field);

                if (!empty($fieldErrors)) {
                    $errors[$path] = $fieldErrors;
                }
            }
        }

        return $errors;
    }

    /**
     * Error messages of a field.
     *
     * @param string $field      Field name, or path of a nested field
     * @param mixed  $value      Value of the field
     * @param array  $fieldRules Rules of the field
     * @param array  $data       Data to validate
     * @param array  $messages   Custom messages
     * @param string $pattern    Field name in the rules, like `items.*.quantity`
     *
     * @return string[]
     */
    private function checkField(string $field, $value, array $fieldRules, array $data, array $messages, string $pattern): array
    {
        $errors = [];

        // custom messages of nested fields can use the path or the pattern
        if ($pattern !== $field) {
            foreach ($messages as $key => $message) {
                $key = (string) $key;
                $concrete = $field.substr($key, strlen($pattern));

                if (0 === strpos($key, $pattern.'.') && !isset($messages[$concrete])) {
                    $messages[$concrete] = $message;
                }
            }
        }

        $empty = is_null($value) || '' === $value || [] === $value;

        if ($empty) {
            if (in_array('required', $fieldRules, true)) {
                $errors[] = $this->message('required', null, $field, $messages);
            }

            return $errors;
        }

        // sizes are numeric for numeric fields
        $numeric = in_array('integer', $fieldRules, true) || in_array('numeric', $fieldRules, true);

        foreach ($fieldRules as $key => $rule) {
            if (is_string($key)) {
                $name = $key;
                $param = $rule;
            } elseif (is_string($rule)) {
                $name = $rule;
                $param = null;
            } elseif (is_callable($rule)) {
                $result = call_user_func($rule, $value, $field, $data);

                if (true !== $result) {
                    $errors[] = is_string($result)
                        ? $this->replace($result, $field, null)
                        : $this->message('custom', null, $field, $messages);
                }

                continue;
            } else {
                throw new InvalidArgumentException(sprintf('Invalid rule for the "%s" field.', $field));
            }

            if ('required' === $name) {
                continue;
            }

            $result = $this->apply($name, $param, $value, $field, $data, $numeric);

            if (true !== $result) {
                $errors[] = is_string($result)
                    ? $this->replace($result, $field, $param)
                    : $this->message($name, $param, $field, $messages);
            }
        }

        return $errors;
    }

    /**
     * Values of a field, nested fields can have more values (one for each item).
     *
     * @return array [path (keys), value, whether the value exists] for each value
     */
    private function expand(string $field, array $data): array
    {
        // a key containing dots is a field too
        if (array_key_exists($field, $data) || false === strpos($field, '.')) {
            return [[[$field], $data[$field] ?? null, array_key_exists($field, $data)]];
        }

        $items = [[[], $data, true]];

        foreach (explode('.', $field) as $segment) {
            $next = [];

            foreach ($items as $item) {
                list($path, $value, $exists) = $item;

                if ('*' !== $segment) {
                    $found = $exists && is_array($value) && array_key_exists($segment, $value);
                    $next[] = [array_merge($path, [$segment]), $found ? $value[$segment] : null, $found];

                    continue;
                }

                // each item of an array, none when it's missing
                if ($exists && is_array($value)) {
                    foreach ($value as $key => $child) {
                        $next[] = [array_merge($path, [$key]), $child, true];
                    }
                }
            }

            $items = $next;
        }

        return $items;
    }

    /**
     * Convert a validated value, when enabled by `castTypes()`.
     *
     * @return mixed
     */
    private function cast($value, array $fieldRules)
    {
        if (!$this->cast || !is_scalar($value) || '' === $value) {
            return $value;
        }

        if (in_array('boolean', $fieldRules, true)) {
            return in_array($value, [true, 1, '1', 'true'], true);
        }

        if (in_array('integer', $fieldRules, true)) {
            return (int) $value;
        }

        if (in_array('numeric', $fieldRules, true) && is_numeric($value)) {
            return $value + 0;
        }

        return $value;
    }

    /**
     * Apply a rule, returning true, false or a message.
     */
    private function apply(string $name, $param, $value, string $field, array $data, bool $numeric)
    {
        if (isset($this->rules[$name])) {
            return call_user_func($this->rules[$name][0], $value, $param, $field, $data);
        }

        switch ($name) {
            case 'string':
                return is_string($value);

            case 'integer':
                return false !== filter_var($value, FILTER_VALIDATE_INT);

            case 'numeric':
                return is_numeric($value);

            case 'boolean':
                return in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true);

            case 'array':
                return is_array($value);

            case 'email':
                return is_string($value) && false !== filter_var($value, FILTER_VALIDATE_EMAIL);

            case 'url':
                return is_string($value) && false !== filter_var($value, FILTER_VALIDATE_URL);

            case 'ip':
                return is_string($value) && false !== filter_var($value, FILTER_VALIDATE_IP);

            case 'date':
                if (!is_string($value)) {
                    return false;
                }

                if (is_null($param)) {
                    return false !== strtotime($value);
                }

                $date = DateTime::createFromFormat('!'.$param, $value);

                return false !== $date && $date->format($param) === $value;

            case 'min':
            case 'max':
            case 'between':
                $size = $this->size($value, $numeric);

                if (is_null($size)) {
                    return false;
                }

                $limits = 'between' === $name ? array_values((array) $param) : [$param, $param];

                if ('max' !== $name && $size < $limits[0]) {
                    return false;
                }

                return 'min' === $name || $size <= $limits[1];

            case 'in':
                return is_scalar($value) && in_array((string) $value, array_map('strval', (array) $param), true);

            case 'regex':
                return is_scalar($value) && 1 === preg_match($param, (string) $value);

            case 'same':
                return array_key_exists($param, $data) && $data[$param] === $value;

            case 'confirmed':
                return array_key_exists($field.'_confirmation', $data) && $data[$field.'_confirmation'] === $value;
        }

        throw new InvalidArgumentException(sprintf('Rule "%s" not defined.', $name));
    }

    /**
     * Size of a value: number of characters, number of items, or the value of numbers.
     *
     * @return float|int|null
     */
    private function size($value, bool $numeric)
    {
        if (is_array($value)) {
            return count($value);
        }

        if (($numeric || is_int($value) || is_float($value)) && is_numeric($value)) {
            return $value + 0;
        }

        if (is_string($value)) {
            return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        }

        return null;
    }

    /**
     * Message of a failed rule, custom messages are checked first.
     */
    private function message(string $name, $param, string $field, array $messages): string
    {
        $message = $messages[$field.'.'.$name]
            ?? $messages[$name]
            ?? (isset($this->rules[$name]) ? $this->rules[$name][1] : null)
            ?? $this->messages[$name];

        return $this->replace($message, $field, $param);
    }

    /**
     * Replace the placeholders of a message.
     */
    private function replace(string $message, string $field, $param): string
    {
        $params = array_values((array) $param);

        return strtr($message, [
            ':field' => str_replace('_', ' ', $field),
            ':param' => implode(', ', array_map('strval', $params)),
            ':min' => (string) ($params[0] ?? ''),
            ':max' => (string) ($params[1] ?? ''),
        ]);
    }
};
