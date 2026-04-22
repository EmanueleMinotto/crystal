The [template engine](https://en.wikipedia.org/wiki/Template_processor) is a super simple way to build user interfaces.<br>
Consider this example: you just want to display an HTML output with a variable name.

Create a file `example.html.php` with the following content:

```html
<!doctype html>
<html>
    <head>
        <title>crystal template engine</title>
    </head>
    <body>
        <p>Hello <?php echo $name; ?>!</p>
        <div><?php echo $escaped_link; ?></div>
    </body>
</html>
```

and render it with the following code:

```php
$mf(function () use ($mf) {
    $tpl = $mf('template');

    echo $tpl->render('example.html.php', array(
        'name' => 'user',
        'escaped_link' => $tpl->e("<a href='test'>Test</a>"),
    ));
});
```

The output you'll obtain for the user is: `Hello user! <a href='test'>Test</a>`.<br />
You'll see also the `a` tag because its content is escaped, so won't be rendered as HTML.
