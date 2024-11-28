--TEST--
Template Engine
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

$mf(function () use ($mf) {
    $tpl = $mf('template');
    $escape = $mf('template:escape');

    echo $tpl(__DIR__.'/template-engine/example.html.php', array(
        'title' => 'Hello World!',
        'name' => $escape("<a href='test'>Test</a>"),
    ));
});

?>
--EXPECT--
<html>
    <head>
        <title>Hello World!</title>
    </head>
    <body>
        <div>&lt;a href=&#039;test&#039;&gt;Test&lt;/a&gt;</div>
    </body>
</html>
