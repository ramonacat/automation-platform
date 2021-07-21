<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();
    $services->set(\SlevomatCodingStandard\Sniffs\Namespaces\AlphabeticallySortedUsesSniff::class);
    $services->set(\SlevomatCodingStandard\Sniffs\Namespaces\ReferenceUsedNamesOnlySniff::class)
        ->property('searchAnnotations', true)
        ->property('searchAnnotations', true)
        ->property('allowFullyQualifiedExceptions', false)
        ->property('allowFullyQualifiedNameForCollidingClasses', true)
        ->property('allowFullyQualifiedNameForCollidingFunctions', true)
        ->property('allowFullyQualifiedNameForCollidingConstants', true)
        ->property('allowFullyQualifiedGlobalClasses', false)
        ->property('allowFullyQualifiedGlobalFunctions', false)
        ->property('allowFullyQualifiedGlobalConstants', false)
        ->property('allowFallbackGlobalFunctions', false)
        ->property('allowFallbackGlobalConstants', false);
    $parameters = $containerConfigurator->parameters();
    $parameters->set(\Symplify\EasyCodingStandard\ValueObject\Option::PATHS, [__DIR__.'/src', __DIR__.'/bin']);
    $containerConfigurator->import(SetList::PSR_12);
    $containerConfigurator->import(SetList::CLEAN_CODE);
    $containerConfigurator->import(SetList::STRICT);
//    $containerConfigurator->import(SetList::SYMPLIFY);
};