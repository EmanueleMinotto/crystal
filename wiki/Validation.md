Crystal validates input data with rules defined as arrays.

```php
$mf->post('/users', function ($request) {
    $data = $request->validate([
        'email' => ['required', 'email', 'max' => 255],
        'name' => ['required', 'string', 'between' => [2, 100]],
        'age' => ['integer', 'min' => 18],
        'role' => ['required', 'in' => ['admin', 'editor']],
        'password' => ['required', 'min' => 8, 'confirmed'],
    ]);

    // $data contains only the validated fields
});
```

Rules without parameters are values (`'required'`), rules with parameters are keys (`'max' => 255`).

## Validating the request

`$request->validate($rules, $messages)` validates the query string and the body values (from a form or a JSON object) together, the body values take precedence:

```php
$mf->post('/api/users', function ($request, $response) {
    $data = $request->validate([
        'email' => ['required', 'email'],
        'name' => ['required', 'string', 'max' => 100],
    ]);

    return $response->json(['user' => $data], 201);
});
```

Any array can be validated with the `validator` service:

```php
$data = $mf('validator')->validate(['email' => 'alice@example.com'], ['email' => ['required', 'email']]);
```

`$request->validate()` is the same as `$mf('validator')->validate(array_replace($request->query(), $request->input()), $rules, $messages)`, but a malformed JSON body throws an exception with status code 400 (a 400 response with the [[Errors]] handling enabled) instead of being validated as an empty body.

## Results

`validate()` returns the fields having rules and present in the data, ignoring the other ones.
When the data isn't valid, it throws an exception with status code 422: with the [[Errors]] handling enabled the client receives a 422 response listing the errors.

```json
{"error":{"status":422,"title":"Unprocessable Content","message":"The given data is invalid.","errors":{"email":["The email field must be a valid email address."]}}}
```

The errors of the exception are available with `getErrors()`.

`check()` returns the errors instead of throwing an exception, useful for HTML forms showing the errors next to the fields:

```php
$rules = ['email' => ['required', 'email'], 'message' => ['required', 'max' => 1000]];

$mf->post('/contact', function ($request, $response, $validator, $template) use ($rules) {
    $errors = $validator->check($request->input(), $rules);

    if (!empty($errors)) {
        // the form again, with the errors and the values sent
        return $response->setStatus(422)->setBody($template->render(__DIR__.'/templates/contact.html.php', [
            'errors' => $errors, // ['email' => ['The email field is required.']]
            'old' => $request->input(),
        ]));
    }

    // send the message...
});
```

```html
<input name="email" value="<?php echo $this->e($old['email'] ?? ''); ?>">
<?php foreach ($errors['email'] ?? [] as $error): ?>
    <p class="error"><?php echo $this->e($error); ?></p>
<?php endforeach; ?>
```

All the errors of each field are returned, see the [contact form](Cookbook-Contact-Form) of the [[Cookbook]] for a complete example.

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
$rules = [
    'password' => ['required', function ($value, $field, $data) {
        return $value !== $data['username'] ? true : 'The :field field must differ from the username.';
    }],
];
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

Messages of nested fields can use the pattern (`items.*.sku.required`) or the path of an item (`items.0.sku.required`).

## Nested fields

Dots select the keys of arrays (like a JSON body), `*` each item of an array:

```php
$data = $mf('request')->validate([
    'address.city' => ['required', 'string'],
    'items' => ['required', 'array'],
    'items.*.sku' => ['required', 'string'],
    'items.*.quantity' => ['integer', 'min' => 1],
]);
```

-   errors use the path of each item: `items.1.quantity`
-   `*` on a missing array or on a value that isn't an array checks nothing, so add rules to the array itself (`'items' => ['required', 'array']`)
-   the validated data keeps the structure: `['address' => ['city' => 'Rome'], 'items' => [...]]`, with only the validated keys of nested arrays (unless the array has rules too)
-   keys containing dots are still fields: when the data has a `tags.main` key, it's validated as a single field

## Types of the validated values

Values from forms and query strings are strings, `castTypes()` converts the validated values of `integer`, `numeric` and `boolean` fields:

```php
$mf('validator')->castTypes();

$mf('validator')->validate(['age' => '30', 'price' => '9.90', 'active' => 'false'], [
    'age' => ['integer'],
    'price' => ['numeric'],
    'active' => ['boolean'],
]);
// ['age' => 30, 'price' => 9.9, 'active' => false]
```

Empty values aren't converted. The conversion is disabled by default and it applies to `request->validate()` too.
