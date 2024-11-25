--TEST--
Template Engine
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

$mf(function () use ($mf) {
    $tpl = $mf('template_engine');

    echo $tpl(__DIR__.'/template-engine/example.html.php', array(
        'title' => 'Hello World!',
    ));
});

?>
--EXPECT--
<html>
    <head>
        <title>Hello World!</title>
    </head>
</html>
