<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude('vendor')
    ->exclude('tools')
    ->in(__DIR__)
;

$config = new PhpCsFixer\Config();
return $config->setRules([
    '@PSR12' => true,
    'normalize_index_brace' => true,
    'trim_array_spaces' => true,
    'whitespace_after_comma_in_array' => true,
    'braces' => true,
    'constant_case' => true,
    'final_class' => true,
    'array_syntax' => ['syntax' => 'short'],
])
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ;