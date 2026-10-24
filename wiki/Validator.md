The `validator` service validates arrays of data, failures result in a `422 Unprocessable Content` [[error|Errors]] listing the messages for each field.

```php
$mf->post('/users', function () use ($mf) {
    $data = $mf('request')->validate([
        'email' => 'required|email',
        'name' => 'required|string|between:2,64',
        'age' => ['nullable', 'integer', 'min:18'],
        'role' => 'in:admin,user',
        'address.city' => 'required|string',
    ]);

    // $data contains only the validated fields
});
```

```json
{
    "type": "about:blank",
    "title": "Unprocessable Content",
    "status": 422,
    "detail": "The given data was invalid.",
    "errors": {
        "email": ["The email field must be a valid email address."]
    }
}
```

## Rules

| Rule | Description |
|---|---|
| `required` | the field must be present and not empty |
| `nullable` | the field can be null or missing (default for not required fields) |
| `string`, `integer`, `numeric`, `boolean`, `array` | type checks, `integer` and `numeric` accept numeric strings |
| `email`, `url`, `uuid`, `date` | format checks |
| `min:n`, `max:n`, `between:min,max` | numbers are compared by value (with `integer` or `numeric`), arrays by count, strings by length |
| `in:a,b,c` | the value must be one of the list |
| `regex:pattern` | e.g. `regex:/^\d{5}$/`, use the array syntax if the pattern contains `\|` |
| `confirmed` | the `<field>_confirmation` field must have the same value |

Custom rules are callables returning `true` or an error message:
```php
'code' => ['required', function ($value, $field, $data) {
    return 0 === strpos($value, 'X') ?: 'The code must start with X.';
}],
```

## Messages

```php
$mf('validator')->validate($data, $rules, [
    'email.required' => 'We need your email.', // field and rule
    'required' => 'Please fill {field}.',      // rule only
]);
```

## Without exceptions

```php
$errors = $mf('validator')->errors($data, $rules); // [] if valid
```
