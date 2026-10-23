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

Inside a template `$this` is the template engine, so `$this->e($value)` can be used directly.
Escaping arrays or objects without a `__toString` method throws an `InvalidArgumentException`.

## Errors

-   rendering a template that doesn't exist throws an `InvalidArgumentException`
-   if a template throws an exception, its partial output is discarded and the exception is propagated
-   template data can't overwrite the engine internals (e.g. a `template` or `this` key)

## Layouts and sections

A template can be wrapped into a layout calling `$this->layout()`.
The output of the template is available in the layout as the `content` section,
other sections are captured between `$this->start()` and `$this->stop()`.

Create a layout `layout.html.php`:

```html
<!doctype html>
<html>
    <head>
        <title><?php echo $this->section('title', 'Default title'); ?></title>
    </head>
    <body>
        <?php echo $this->section('content'); ?>
    </body>
</html>
```

and a page `page.html.php` using it:

```html
<?php $this->layout('layout.html.php', array('lang' => 'en')); ?>

<?php $this->start('title'); ?>Hello <?php echo $this->e($name); ?><?php $this->stop(); ?>

<p>Hello <?php echo $this->e($name); ?>!</p>
```

then render the page as usual with `$tpl->render('page.html.php', array('name' => 'user'))`.

-   the layout receives the data of the template, merged with the data passed to `$this->layout()`
-   layouts can use other layouts, building a multi-level inheritance
-   `$this->section($name, $default)` returns `$default` when the section isn't defined
-   a section defined by a template has precedence over the same section defined by its layouts
-   `content` is a reserved section name, sections not closed throw a `LogicException`
