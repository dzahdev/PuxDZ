<?php

// declare_strict_types stays off until Stage 5, when every module gets strict types under a
// green suite (plan invariant 6).

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/eg',
    ]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PHP82Migration' => true,
        'declare_strict_types' => false,
    ])
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
    ->setFinder($finder);
