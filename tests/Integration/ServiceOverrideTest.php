<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Integration;

use Bakslash\StaticSiteBundle\Tests\Fixtures\CustomDumper;
use Bakslash\StaticSiteBundle\Tests\Fixtures\CustomRenderer;
use Bakslash\StaticSiteBundle\Tests\Fixtures\CustomServicesKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ServiceOverrideTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return CustomServicesKernel::class;
    }

    public function testRendererAndDumperCanBeOverridden(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->assertInstanceOf(CustomRenderer::class, $container->get('bakslash_static_site.renderer'));
        $this->assertInstanceOf(CustomDumper::class, $container->get('bakslash_static_site.dumper'));
    }
}
