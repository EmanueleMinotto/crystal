<?php

/**
 * Exposes an input validator via $deps['validator'].
 *
 * Rules are defined as a pipe separated string or as a list, custom rules
 * are callables returning true or an error message:
 *
 *   [
 *       'email' => 'required|email',
 *       'age' => ['nullable', 'integer', 'min:18'],
 *       'address.city' => 'required|string|max:64',
 *       'code' => ['required', function ($value, $field, $data) { return ... ?: 'Invalid code.'; }],
 *   ]
 *
 * Available rules: required, nullable, string, integer, numeric, boolean,
 * array, email, url, uuid, date, min:n, max:n, between:min,max, in:a,b,c,
 * regex:pattern, confirmed.
 */
$deps['validator'] = new class ($this) {
    /**
     * Framework instance, used to resolve other services.
     *
     * @var callable
     */
    private $mf;

    /**
     * Default error messages.
     *
     * @var array
     */
    private $messages = [
        'required' => 'The {field} field is required.',
        'string' => 'The {field} field must be a string.',
        'integer' => 'The {field} field must be an integer.',
        'numeric' => 'The {field} field must be a number.',
        'boolean' => 'The {field} field must be true or false.',
        'array' => 'The {field} field must be an array.',
        'email' => 'The {field} field must be a valid email address.',
        'url' => 'The {field} field must be a valid URL.',
        'uuid' => 'The {field} field must be a valid UUID.',
        'date' => 'The {field} field must be a valid date.',
        'min' => 'The {field} field must be at least {0}.',
        'max' => 'The {field} field must not be greater than {0}.',
        'between' => 'The {field} field must be between {0} and {1}.',
        'in' => 'The selected {field} is invalid.',
        'regex' => 'The {field} field format is invalid.',
        'confirmed' => 'The {field} field confirmation does not match.',
    ];

    public function __construct(callable $mf)
    {
        $this->mf = $mf;
    }

    /**
     * Validate data and return the validated fields.
     *
     * @param array $data     Data to validate
     * @param array $rules    Rules indexed by field name, dot notation allowed
     * @param array $messages Custom messages indexed by `field.rule` or `rule`
     *
     * @throws RuntimeException A 422 error with the `errors` member if validation fails
     *
     * @return array
     */
    public function validate(array $data, array $rules, array $messages = [])
    {
        $errors = $this->errors($data, $rules, $messages);

        if (!empty($errors)) {
            throw call_user_func($this->mf, 'errors')->create(422, 'The given data was invalid.', ['errors' => $errors]);
        }

        $validated = [];

        foreach (array_keys($rules) as $field) {
            if ($this->has($data, $field)) {
                $this->set($validated, $field, $this->get($data, $field));
            }
        }

        return $validated;
    }

    /**
     * Validate data and return error messages indexed by field name.
     *
     * @return array
     */
    public function errors(array $data, array $rules, array $messages = [])
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $fieldRules = is_string($fieldRules) ? explode('|', $fieldRules) : (array) $fieldRules;
            $present = $this->has($data, $field);
            $value = $this->get($data, $field);
            $empty = !$present || is_null($value) || '' === $value || [] === $value;

            if ($empty) {
                if (in_array('required', $fieldRules, true)) {
                    $errors[$field][] = $this->message('required', $field, [], $messages);
                }

                continue;
            }

            $type = $this->type($fieldRules);

            foreach ($fieldRules as $rule) {
                if (is_callable($rule) && !is_string($rule)) {
                    $result = call_user_func($rule, $value, $field, $data);

                    if (true !== $result) {
                        $errors[$field][] = is_string($result) ? $result : $this->message('regex', $field, [], $messages);
                    }

                    continue;
                }

                $parts = explode(':', $rule, 2);
                $name = $parts[0];
                $args = isset($parts[1]) ? ('regex' === $name ? [$parts[1]] : explode(',', $parts[1])) : [];

                if (in_array($name, ['required', 'nullable'], true)) {
                    continue;
                }

                if (!isset($this->messages[$name])) {
                    throw new InvalidArgumentException(sprintf('Unknown validation rule "%s"', $name));
                }

                if (!$this->check($name, $value, $args, $type, $data, $field)) {
                    $errors[$field][] = $this->message($name, $field, $args, $messages);
                }
            }
        }

        return $errors;
    }

    private function check(string $rule, $value, array $args, string $type, array $data, string $field)
    {
        switch ($rule) {
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

            case 'uuid':
                return is_string($value) && preg_match('#^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$#i', $value);

            case 'date':
                return is_string($value) && false !== strtotime($value);

            case 'min':
                return $this->size($value, $type) >= $args[0];

            case 'max':
                return $this->size($value, $type) <= $args[0];

            case 'between':
                $size = $this->size($value, $type);

                return $size >= $args[0] && $size <= $args[1];

            case 'in':
                return is_scalar($value) && in_array((string) $value, $args, true);

            case 'regex':
                return is_scalar($value) && preg_match($args[0], (string) $value);

            case 'confirmed':
                return $this->has($data, $field.'_confirmation') && $this->get($data, $field.'_confirmation') === $value;
        }
    }

    /**
     * Type used to compare sizes.
     */
    private function type(array $rules)
    {
        foreach ($rules as $rule) {
            if (in_array($rule, ['integer', 'numeric'], true)) {
                return 'numeric';
            }

            if ('array' === $rule) {
                return 'array';
            }
        }

        return 'string';
    }

    private function size($value, string $type)
    {
        if (is_array($value)) {
            return count($value);
        }

        if ('numeric' === $type && is_numeric($value)) {
            return $value + 0;
        }

        $value = (string) $value;

        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    private function message(string $rule, string $field, array $args, array $messages)
    {
        if (isset($messages[$field.'.'.$rule])) {
            $message = $messages[$field.'.'.$rule];
        } elseif (isset($messages[$rule])) {
            $message = $messages[$rule];
        } else {
            $message = $this->messages[$rule];
        }

        $replace = ['{field}' => str_replace('_', ' ', $field)];

        foreach ($args as $i => $arg) {
            $replace['{'.$i.'}'] = $arg;
        }

        return strtr($message, $replace);
    }

    private function has(array $data, string $field)
    {
        if (array_key_exists($field, $data)) {
            return true;
        }

        foreach (explode('.', $field) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return false;
            }

            $data = $data[$segment];
        }

        return true;
    }

    private function get(array $data, string $field)
    {
        if (array_key_exists($field, $data)) {
            return $data[$field];
        }

        foreach (explode('.', $field) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return null;
            }

            $data = $data[$segment];
        }

        return $data;
    }

    private function set(array &$data, string $field, $value)
    {
        $keys = explode('.', $field);

        while (count($keys) > 1) {
            $key = array_shift($keys);

            if (!isset($data[$key]) || !is_array($data[$key])) {
                $data[$key] = [];
            }

            $data = &$data[$key];
        }

        $data[array_shift($keys)] = $value;
    }
};
