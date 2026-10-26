<?php

// the templates used by the tests are fixtures, their output must not change
$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__, __DIR__.'/../tests'])
    ->exclude('template-engine')
    ->append([__DIR__.'/../crystal.php'])
;

return (new PhpCsFixer\Config())
    ->setUsingCache(false)
    ->setRules([
        '@PSR12' => true,
        '@PHP7x0Migration' => true,
        'align_multiline_comment' => true,
        'blank_line_before_statement' => [
            'statements' => ['break', 'case', 'continue', 'declare', 'default', 'do', 'exit', 'for', 'foreach', 'goto', 'if', 'include', 'include_once', 'phpdoc', 'require', 'require_once', 'return', 'switch', 'throw', 'try', 'while', 'yield', 'yield_from'],
        ],
        'no_extra_blank_lines' => [
            'tokens' => ['attribute', 'break', 'case', 'continue', 'curly_brace_block', 'default', 'extra', 'parenthesis_brace_block', 'return', 'square_brace_block', 'switch', 'throw', 'use', 'use_trait'],
        ],
        'phpdoc_align' => true,
    ])
    ->setFinder($finder)
;
