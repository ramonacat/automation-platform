<?php

declare(strict_types=1);

use SlevomatCodingStandard\Sniffs\Namespaces\AlphabeticallySortedUsesSniff;
use SlevomatCodingStandard\Sniffs\Namespaces\ReferenceUsedNamesOnlySniff;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symplify\EasyCodingStandard\ValueObject\Option;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();
    $services->set(AlphabeticallySortedUsesSniff::class);
    $services->set(ReferenceUsedNamesOnlySniff::class)
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
    $parameters->set(Option::PATHS, [
        __DIR__ . '/src',
//        __DIR__ . '/build.php',
        __DIR__ . '/ecs.php',
    ]);
    $containerConfigurator->import(SetList::PSR_12);
    $containerConfigurator->import(SetList::CLEAN_CODE);
    $containerConfigurator->import(SetList::STRICT);
};
