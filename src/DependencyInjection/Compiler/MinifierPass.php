<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\DependencyInjection\Compiler;

use Bakslash\StaticSiteBundle\Event\PageRenderedEvent;
use Bakslash\StaticSiteBundle\EventListener\MinifyListener;
use Bakslash\StaticSiteBundle\Exception\LogicException;
use Sensiolabs\MinifyBundle\Minifier\MinifierInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers the minifying listener when minification is enabled.
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

        // Runs late (negative priority) so it minifies the final content, after any
        // user PageRenderedEvent listeners have transformed the page.
        $container->register('.bakslash_static_site.minify_listener', MinifyListener::class)
            ->setArguments([new Reference(MinifierInterface::class)])
            ->addTag('kernel.event_listener', [
                'event' => PageRenderedEvent::class,
                'method' => '__invoke',
                'priority' => -256,
            ]);
    }
}
