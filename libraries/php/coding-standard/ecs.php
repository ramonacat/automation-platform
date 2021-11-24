<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\ClassNotation\FinalClassFixer;
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
        'build-config.php',
        'ecs.php',
    ]);
    $containerConfigurator->import(SetList::PSR_12);
    $containerConfigurator->import(SetList::CLEAN_CODE);
    $containerConfigurator->import(SetList::STRICT);
    $services->remove(AlphabeticallySortedUsesSniff::class);
    $services->set(FinalClassFixer::class);
};
