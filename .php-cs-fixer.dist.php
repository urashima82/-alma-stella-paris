<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__.'/src')
    ->in(__DIR__.'/tests')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'declare_strict_types' => true,
        'native_function_invocation' => [
            'include' => ['@all'],
        ],
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder)
;
