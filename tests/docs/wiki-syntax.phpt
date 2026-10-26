--TEST--
PHP code blocks of the wiki have a valid syntax
--FILE--
<?php

require __DIR__.'/helpers.php';

$invalid = 0;

foreach (glob(__DIR__.'/../../wiki/*.md') as $path) {
    $page = basename($path, '.md');

    foreach (docs_blocks($page) as $position => $block) {
        if ('php' !== $block['lang']) {
            continue;
        }

        $code = 0 === strpos($block['code'], '<?php') ? $block['code'] : "<?php\n".$block['code'];

        try {
            token_get_all($code, TOKEN_PARSE);
        } catch (ParseError $exception) {
            ++$invalid;
            echo $page.', block '.($position + 1).': '.$exception->getMessage().' on line '.$exception->getLine()."\n";
        }
    }
}

echo $invalid.' invalid blocks';

?>
--EXPECT--
0 invalid blocks
