<?php

declare(strict_types=1);

/*
 * PHP-CS-Fixer Configuration
 * Deschide News Backend - Symfony 7.3
 *
 * Ruleset: PER-CS 2.0 (PHP Evolving Recommendation)
 * Standard: PSR-12 successor for modern PHP 8+
 *
 * Documentation: https://cs.symfony.com/doc/ruleSets/PER-CS2.0.html
 */

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude([
        'var',
        'vendor',
        'public/bundles',
        'dev-tools',
    ])
    ->notPath([
        'migrations/',
        'config/bundles.php',
    ])
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        // Base ruleset: PER-CS 2.0 (PSR-12 successor)
        '@PER-CS2x0' => true,

        // Additional Symfony conventions
        '@Symfony' => true,

        // Doctrine annotation support
        '@DoctrineAnnotation' => true,

        // Modern PHP features
        '@PHP8x4Migration' => true,

        // Strict types declaration
        'declare_strict_types' => true,

        // Array syntax
        'array_syntax' => ['syntax' => 'short'],

        // Whitespace and formatting
        'blank_line_after_opening_tag' => true,
        'blank_line_before_statement' => [
            'statements' => ['return', 'throw', 'try'],
        ],
        'no_extra_blank_lines' => [
            'tokens' => [
                'extra',
                'throw',
                'use',
            ],
        ],

        // Imports optimization
        'no_unused_imports' => true,
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['class', 'function', 'const'],
        ],
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => false,
            'import_functions' => false,
        ],

        // Phpdoc improvements
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_order' => true,
        'phpdoc_separation' => true,
        'phpdoc_trim' => true,
        'phpdoc_types_order' => [
            'null_adjustment' => 'always_last',
            'sort_algorithm' => 'none',
        ],

        // Method and function improvements
        'method_chaining_indentation' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'return_type_declaration' => ['space_before' => 'none'],

        // Class and property improvements
        'class_attributes_separation' => [
            'elements' => [
                'const' => 'one',
                'method' => 'one',
                'property' => 'one',
                'trait_import' => 'none',
            ],
        ],
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'constant_public',
                'constant_protected',
                'constant_private',
                'property_public',
                'property_protected',
                'property_private',
                'construct',
                'destruct',
                'magic',
                'phpunit',
                'method_public',
                'method_protected',
                'method_private',
            ],
        ],

        // String improvements
        'single_quote' => true,
        'string_implicit_backslashes' => true,

        // Strict comparisons (risky but recommended)
        'strict_comparison' => true,
        'strict_param' => true,

        // Native function invocation (risky but performance improvement)
        'native_function_invocation' => [
            'include' => ['@compiler_optimized'],
            'scope' => 'namespaced',
            'strict' => true,
        ],

        // Modernize code
        'modernize_types_casting' => true,
        'no_alias_functions' => true,
        'no_php4_constructor' => true,

        // Disable some rules that conflict with Symfony conventions
        'concat_space' => ['spacing' => 'one'],
        'yoda_style' => false,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/var/cache/.php-cs-fixer.cache')
;
