--TEST--
Templates found in a directory, with shared data
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

$mf(function () use ($mf) {
    $template = $mf('template');
    $template->setDirectory(__DIR__.'/template-engine/');
    $template->share('title', 'Shared title');
    $template->share(['name' => 'Shared name']);

    // the extension can be omitted, data passed to render() takes precedence
    echo $template->render('example', ['name' => 'Alice']);
    echo $template->render('example.html.php');

    // absolute paths are used as they are
    echo strlen($template->render(__DIR__.'/template-engine/example.html.php')) > 0 ? "absolute\n" : '';

    try {
        $template->render('missing');
    } catch (InvalidArgumentException $exception) {
        echo $exception->getMessage()."\n";
    }
});

?>
--EXPECT--
<html>
    <head>
        <title>Shared title</title>
    </head>
    <body>
        <div>Alice</div>
    </body>
</html>
<html>
    <head>
        <title>Shared title</title>
    </head>
    <body>
        <div>Shared name</div>
    </body>
</html>
absolute
Template "missing" not found.
