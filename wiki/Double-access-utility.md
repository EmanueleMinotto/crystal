This is an utility made to access to common global variables in a mode I prefer:

`$mf('get')->foo` against `$_GET['foo']`.

Where's the utility?
 * you can use this function for other variables too
 * backward compatibility: array access is allowed too, so `$_GET['foo']` is accepted
 * integer and float values will be converted from string to their respective values
 * usage on inclusion
 * prevent not defined cells errors


## Globals
The are some predefined [[Utilities]] (like `$mf('get')`,  `$mf('post')`,  `$mf('request')`, etc...) containing the globals `$_GET`, `$_POST`, `$_REQUEST`, etc...

If a variable is not defined you don't have to catch errors because undefined cells will return `null`.
```php
// URL is http://hostname/path?lorem=ipsum

$function = $fn('utils:double-access');

var_dump(
    $function($_GET),
    $function($_GET)->lorem,
    $function($_GET)['lorem'],
    $function($_GET)['undefined']
);

// object(ArrayObject)[3]
//   public 'lorem' => string 'ipsum' (length=5)
// string 'ipsum' (length=5)
// string 'ipsum' (length=5)
// null

var_dump(
    $mf('get'),
    $mf('get')->lorem,
    $mf('get')['lorem'],
    $mf('get')['undefined']
);

// object(ArrayObject)[3]
//   public 'lorem' => string 'ipsum' (length=5)
// string 'ipsum' (length=5)
// string 'ipsum' (length=5)
// null
```

## Usage on other variables
```php
$function = $fn('utils:double-access');

$tmp = array(
    'foo' => array(
        0 => 'bar',
        1 => 5,
        'test' => 6.42,
    ),
);

$function($tmp);

var_dump(
    $tmp['foo'],
    $tmp->foo,
    $tmp['foo'][0],
    $tmp->foo[0],
    $tmp->foo->test
);

// object(ArrayObject)[1]
//   string 'bar' (length=3)
//   int 5
//   public 'test' => float 6.42
// object(ArrayObject)[1]
//   string 'bar' (length=3)
//   int 5
//   public 'test' => float 6.42
// string 'bar' (length=3)
// string 'bar' (length=3)
// float 6.42
```
