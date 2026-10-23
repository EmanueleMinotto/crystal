--TEST--
Template Engine layouts and sections
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

$mf(function () use ($mf) {
    $tpl = $mf('template');

    echo $tpl->render(__DIR__.'/template-engine/layout.html.php', array(
        'title' => 'Hello <World>',
        'name' => 'user',
    ));
    echo $tpl->render(__DIR__.'/template-engine/nested-layout.html.php');
});

?>
--EXPECT--
<html>
    <head>
        <title>Hello &lt;World&gt;</title>
    </head>
    <body>
        <p>Hello user!</p>
        <footer>Default footer</footer>
    </body>
</html>
<html>
    <head>
        <title>Default title</title>
    </head>
    <body>
        <main>
            <p>Nested</p>
        </main>
        <footer>Child footer</footer>
    </body>
</html>
