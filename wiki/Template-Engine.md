The `template` service renders PHP files: templates are plain PHP and HTML, with layouts and sections.

`templates/hello.html.php`:

```html
<!doctype html>
<html>
    <body>
        <p>Hello <?php echo $this->e($name); ?>!</p>
    </body>
</html>
```

```php
$mf->get('/hello/{name}', function ($name, $template) {
    echo $template->render(__DIR__.'/templates/hello.html.php', ['name' => $name]);
});

// GET /hello/Alice
// <p>Hello Alice!</p>
```

`render($file, $data)` returns the output as a string: the keys of `$data` are the variables of the template, and `$this` is the template service.
Files are found by their path, or in the directories of the [`include_path`](https://www.php.net/manual/en/ini.core.php#ini.include-path).

## Directory and shared data

`setDirectory()` finds the templates with a relative path in a directory, the `.html.php` extension can be omitted.
`share()` makes values available in every template:

```php
$template = $mf('template');
$template->setDirectory(__DIR__.'/templates');
$template->share('csrf', $mf('csrf'));
$template->share(['site' => 'My blog', 'year' => date('Y')]);

$mf->get('/hello/{name}', function ($name, $template) {
    echo $template->render('hello', ['name' => $name]); // templates/hello.html.php
});
```

Values passed to `render()` with the same name take precedence over the shared ones.
Layouts and partials are found in the directory too, absolute paths are used as they are.

## Escaping

**Always escape the values** printed in the HTML with `$this->e()`, so they can't add tags or scripts ([XSS](https://owasp.org/www-community/attacks/xss/)):

```html
<p><?php echo $this->e($comment); ?></p>
<a href="<?php echo $this->e($url); ?>" title="<?php echo $this->e($title); ?>">Link</a>
```

With `$comment` equal to `<script>alert(1)</script>`, the page shows the text `<script>alert(1)</script>` instead of running it.
`e()` uses `htmlspecialchars` with `ENT_QUOTES`, so it's safe in attributes too. Escaping arrays or objects without a `__toString` method throws an `InvalidArgumentException`.

Outside the templates, use `$mf('template')->e($value)`.

## Loops and conditions

PHP's [alternative syntax](https://www.php.net/manual/en/control-structures.alternative-syntax.php) keeps templates readable:

```html
<?php if (empty($posts)): ?>
    <p>No posts yet.</p>
<?php else: ?>
    <ul>
        <?php foreach ($posts as $post): ?>
            <li><a href="/posts/<?php echo $this->e($post['id']); ?>"><?php echo $this->e($post['title']); ?></a></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
```

## Partials

A template can render other templates, like a piece of the page used in many places:

```html
<?php foreach ($posts as $post): ?>
    <?php echo $this->render(__DIR__.'/partials/post.html.php', ['post' => $post]); ?>
<?php endforeach; ?>
```

## Layouts and sections

A template can be wrapped in a layout calling `$this->layout()`.
The output of the template is the `content` section of the layout, other sections are captured between `$this->start()` and `$this->stop()`.

`templates/layout.html.php`:

```html
<!doctype html>
<html lang="<?php echo $this->e($lang); ?>">
    <head>
        <title><?php echo $this->section('title', 'My site'); ?></title>
    </head>
    <body>
        <?php echo $this->section('content'); ?>
    </body>
</html>
```

`templates/page.html.php`:

```html
<?php $this->layout(__DIR__.'/layout.html.php', ['lang' => 'en']); ?>

<?php $this->start('title'); ?>Hello <?php echo $this->e($name); ?><?php $this->stop(); ?>

<p>Hello <?php echo $this->e($name); ?>!</p>
```

```php
echo $mf('template')->render(__DIR__.'/templates/page.html.php', ['name' => 'Alice']);
```

-   the layout receives the data of the template, merged with the data passed to `$this->layout()`
-   layouts can use other layouts, building a multi-level inheritance
-   `$this->section($name, $default)` returns `$default` when the section isn't defined
-   a section defined by a template has precedence over the same section defined by its layouts
-   `content` is a reserved section name, sections not closed throw a `LogicException`

## Errors

-   rendering a template that doesn't exist throws an `InvalidArgumentException`
-   if a template throws an exception, its partial output is discarded and the exception is propagated
-   template data can't overwrite the engine internals (e.g. a `this` key)

## Other uses

The output is a string, so it can be used for anything, like the body of an email (see [[Mailer]]):

```php
$mf('mailer')->send([
    'to' => 'alice@example.com',
    'subject' => 'Welcome!',
    'html' => $mf('template')->render(__DIR__.'/templates/emails/welcome.html.php', ['name' => 'Alice']),
]);
```
