--TEST--
Template Engine errors and output buffers cleanup
--SKIPIF--
<?php if (PHP_MAJOR_VERSION < 7) die('Skip: PHP 7+ is required'); ?>
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

$mf(function () use ($mf) {
    $tpl = $mf('template');
    $level = ob_get_level();

    foreach (array('exception', 'unclosed-section', 'missing') as $name) {
        try {
            $tpl->render(__DIR__.'/template-engine/'.$name.'.html.php');
        } catch (Exception $e) {
            echo get_class($e).': '.basename($e->getMessage()).PHP_EOL;
        }

        var_dump(ob_get_level() === $level);
    }

    try {
        $tpl->stop();
    } catch (LogicException $e) {
        echo $e->getMessage().PHP_EOL;
    }

    try {
        $tpl->start('content');
    } catch (LogicException $e) {
        echo $e->getMessage().PHP_EOL;
    }

    // sections don't leak between renders
    var_dump($tpl->section('title'));

    // template variables can't overwrite the engine internals
    echo $tpl->render(__DIR__.'/template-engine/scope.html.php', array(
        'template' => 'a',
        'data' => 'b',
        'this' => 'c',
    )).PHP_EOL;

    var_dump($tpl->e(42), $tpl->e(null), $tpl->e(1.5));

    try {
        $tpl->e(array());
    } catch (InvalidArgumentException $e) {
        echo $e->getMessage().PHP_EOL;
    }
});

?>
--EXPECT--
RuntimeException: Template failure
bool(true)
LogicException: Section "title" not closed.
bool(true)
InvalidArgumentException: missing.html.php" not found.
bool(true)
No section started.
Section name "content" is reserved.
string(0) ""
a|b
string(2) "42"
string(0) ""
string(3) "1.5"
Value of type "array" can't be escaped.
