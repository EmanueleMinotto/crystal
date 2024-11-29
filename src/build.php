<?php

$content = file_get_contents(__DIR__.'/main.php');

function get_php_only($file)
{
    return str_replace('<?php', '', file_get_contents($file));
}

function replace_placeholders($content)
{
    $content = str_replace(
        '### AUTOLOADER ###',
        get_php_only(__DIR__.'/autoloader.php'),
        $content
    );

    $php5files = glob(__DIR__.'/php5/*.php');
    $raw = '';

    foreach ($php5files as $php5file) {
        $raw .= PHP_EOL . get_php_only($php5file);
    }

    $content = str_replace('### PHP 5 FUNCTIONS PLACEHOLDER ###', $raw, $content);

    $php5files = glob(__DIR__.'/php7/*.php');
    $raw = '';

    foreach ($php5files as $php5file) {
        $raw .= PHP_EOL . get_php_only($php5file);
    }

    $content = str_replace('### PHP 7 FEATURES PLACEHOLDER ###', $raw, $content);

    for ($i = 0; $i < 5; $i++) {
        $content = str_replace("\n\n\n", "\n\n", $content);
    }

    return $content;
}

file_put_contents(
    __DIR__.'/../crystal.php',
    replace_placeholders($content)
);
