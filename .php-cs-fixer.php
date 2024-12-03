<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->ignoreVCS(true)
;

return (new PhpCsFixer\Config())
    ->setUsingCache(false)
    ->setRules([
        '@PSR12' => true,
        'align_multiline_comment' => true,
        'array_syntax' => [
            'syntax' => 'long'
        ],
        'blank_line_before_statement' => [
            'statements' => ['break', 'case', 'continue', 'declare', 'default', 'do', 'exit', 'for', 'foreach', 'goto', 'if', 'include', 'include_once', 'phpdoc', 'require', 'require_once', 'return', 'switch', 'throw', 'try', 'while', 'yield', 'yield_from'],
        ],
        'mb_str_functions' => true,
        'no_extra_blank_lines' => [
            'tokens' => ['attribute', 'break', 'case', 'continue', 'curly_brace_block', 'default', 'extra', 'parenthesis_brace_block', 'return', 'square_brace_block', 'switch', 'throw', 'use', 'use_trait'],
        ],
        'phpdoc_align' => true,
    ])
    ->setFinder($finder)
;
