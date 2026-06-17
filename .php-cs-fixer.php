<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->files()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/include',
        __DIR__ . '/modules',
        __DIR__ . '/config',
        __DIR__ . '/routes',
    ])
    ->append([
        __DIR__ . '/psr-worker.php',
    ])
    ->exclude([
        'vendor',
        'node_modules',
        'temp',
        'logs',
        'dist',
    ])
    ->ignoreDotFiles(false)
    ->ignoreVCS(true);

return new PhpCsFixer\Config()
    ->setUsingCache(false)
    ->setRiskyAllowed(false)
    ->setFinder($finder)
    ->setRules([
        '@PSR12' => true,
        'braces_position' => [
            'allow_single_line_anonymous_functions' => false,
            'allow_single_line_empty_anonymous_classes' => true,
            'anonymous_classes_opening_brace' => 'same_line',
            'anonymous_functions_opening_brace' => 'same_line',
            'classes_opening_brace' => 'next_line_unless_newline_at_signature_end',
            'control_structures_opening_brace' => 'same_line',
            'functions_opening_brace' => 'same_line',
        ],
        'elseif' => true,
        'method_argument_space' => [
            'after_heredoc' => false,
            'attribute_placement' => 'ignore',
            'keep_multiple_spaces_after_comma' => false,
            'on_multiline' => 'ensure_fully_multiline',
        ],
        'ordered_imports' => [
            'imports_order' => [
                'class',
                'function',
                'const',
            ],
            'sort_algorithm' => 'alpha',
        ],
        'spaces_inside_parentheses' => [
            'space' => 'none',
        ],
        'trailing_comma_in_multiline' => [
            'after_heredoc' => true,
            'elements' => [
                'arguments',
                'array_destructuring',
                'arrays',
                'match',
                'parameters',
            ],
        ],
        'encoding' => true,
        'class_reference_name_casing' => true,
        'constant_case' => ['case' => 'lower'],
        'lowercase_cast' => true,
        'new_with_parentheses' => [
            'anonymous_class' => false,
            'named_class' => true,
        ],
        'align_multiline_comment' => true,
        'array_indentation' => true,
        'array_syntax' => ['syntax' => 'short'],
        'blank_line_after_namespace' => true,
        'blank_line_after_opening_tag' => true,
        'concat_space' => ['spacing' => 'one'],
        'declare_parentheses' => true,
        'method_chaining_indentation' => true,
        'no_empty_comment' => true,
        'not_operator_with_space' => true,
        'trim_array_spaces' => true,
        'no_empty_phpdoc' => true,
        'no_empty_statement' => true,
        'no_unused_imports' => true,
    ]);
