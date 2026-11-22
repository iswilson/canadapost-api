<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS3x0' => true,
        'declare_strict_types' => true,
        'no_unused_imports' => true,
        'ordered_imports' => [
            'imports_order' => ['class', 'function', 'const'],
        ],
        'phpdoc_separation' => true,
        'phpdoc_types_order' => [
            'null_adjustment' => 'always_last',
        ],
        'phpdoc_line_span' => [
            'const' => 'single',
            'property' => 'single',
            'method' => 'multi',
        ],
        'global_namespace_import' => [
            'import_classes' => false,
            'import_constants' => false,
            'import_functions' => false,
        ],
        'fully_qualified_strict_types' => [
            'import_symbols' => true,
        ],
    ])
    ->setFinder(Finder::create()->in(__DIR__)->exclude('vendor'));
