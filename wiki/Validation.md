Crystal validates input data with rules defined as arrays.

```php
$mf('/users', function () use ($mf) {
    $data = $mf('validator')->validate($mf('request')->input(), [
        'email' => ['required', 'email', 'max' => 255],
        'name' => ['required', 'string', 'between' => [2, 100]],
        'age' => ['integer', 'min' => 18],
        'role' => ['required', 'in' => ['admin', 'editor']],
        'password' => ['required', 'min' => 8, 'confirmed'],
    ]);

    // $data contains only the validated fields
}, 'POST');
```

Rules without parameters are values (`'required'`), rules with parameters are keys (`'max' => 255`).

## Results

`validate()` returns the fields having rules and present in the data, ignoring the other ones.
When the data isn't valid, it throws an exception with status code 422: with the [[Errors]] handling enabled the client receives a 422 response listing the errors.

```json
{"error":{"status":422,"title":"Unprocessable Content","message":"The given data is invalid.","errors":{"email":["The email field must be a valid email address."]}}}
```

The errors of the exception are available with `getErrors()`.

`check()` returns the errors instead of throwing an exception, useful for HTML forms:

```php
$errors = $mf('validator')->check($request->input(), $rules);

if (!empty($errors)) {
    return $mf('response')->setStatus(422)->setBody($mf('template')->render('form.html.php', [
        'errors' => $errors, // ['email' => ['The email field is required.']]
    ]));
}
```

All the errors of each field are returned.

## Empty fields

Fields missing, `null`, empty strings or empty arrays are valid unless `required`: the other rules aren't checked.
So optional fields don't need other rules.

## Rules

| Rule | Description |
| --- | --- |
| `required` | not missing, `null`, empty string or empty array |
| `string`, `integer`, `numeric`, `array` | type, integers and numbers can be strings (`'42'`) |
| `boolean` | `true`, `false`, `0`, `1`, `'0'`, `'1'`, `'true'`, `'false'` |
| `email`, `url`, `ip` | validated with [filter_var](https://www.php.net/manual/en/function.filter-var.php) |
| `date`, `'date' => 'Y-m-d'` | any date accepted by `strtotime`, or a date in the given format |
| `'min' => 3`, `'max' => 3`, `'between' => [2, 5]` | characters of strings, items of arrays, value of numbers (or of `integer`/`numeric` fields) |
| `'in' => ['a', 'b']` | one of the values, compared as strings |
| `'regex' => '/^[a-z]+$/'` | matches the regular expression |
| `'same' => 'field'` | equal to another field |
| `confirmed` | equal to the `<field>_confirmation` field |

## Custom rules

Callables can be used as inline rules, they receive the value, the field name and the data, and return `true`, `false` or an error message:

```php
'password' => ['required', function ($value, $field, $data) {
    return $value !== $data['username'] ? true : 'The :field field must differ from the username.';
}],
```

Reusable rules receive the value, the parameter, the field name and the data:

```php
$mf('validator')->rule('multiple', function ($value, $param) {
    return 0 === $value % $param;
}, 'The :field field must be a multiple of :param.');

$rules = ['count' => ['integer', 'multiple' => 5]];
```

## Messages

Messages can be customized by field and rule, or by rule:

```php
$mf('validator')->validate($data, $rules, [
    'email.required' => 'We need your email address.',
    'required' => 'The :field field is mandatory.',
]);
```

Placeholders: `:field` (underscores become spaces), `:param` (the rule parameter), `:min` and `:max` (for `between`).

Nested fields (like `address.city`) aren't supported yet.
