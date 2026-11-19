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
    ])
    ->setFinder(Finder::create()->in(__DIR__)->exclude('vendor'));
