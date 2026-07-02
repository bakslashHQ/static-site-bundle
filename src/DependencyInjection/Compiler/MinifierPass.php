<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\DependencyInjection\Compiler;

use Bakslash\StaticSiteBundle\Exception\LogicException;
use Bakslash\StaticSiteBundle\Renderer\MinifyingRenderer;
use Sensiolabs\MinifyBundle\Minifier\MinifierInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers the minifying renderer decorator when minification is enabled.
 *
 * @internal
 */
final class MinifierPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->getParameter('bakslash_static_site.minify')) {
            return;
        }

        if (!$container->has(MinifierInterface::class)) {
            throw new LogicException('Package "sensiolabs/minify-bundle" is required to use the "minify" option. Try running "composer require sensiolabs/minify-bundle".');
        }

        $container->register('.bakslash_static_site.renderer.minifying', MinifyingRenderer::class)
            ->setDecoratedService('bakslash_static_site.renderer')
            ->setArguments([
                new Reference('.inner'),
                new Reference(MinifierInterface::class),
            ]);
    }
}
