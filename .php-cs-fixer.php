<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->ignoreVCS(true)
;

return (new PhpCsFixer\Config())
    ->setUsingCache(false)
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => [
            'syntax' => 'long'
        ],
        'no_extra_blank_lines' => true,
    ])
    ->setFinder($finder)
;
