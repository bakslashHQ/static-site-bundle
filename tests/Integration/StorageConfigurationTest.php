<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Integration;

use Bakslash\StaticSiteBundle\Tests\Fixtures\StorageServiceKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class StorageConfigurationTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return StorageServiceKernel::class;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // FrameworkBundle::boot() registers an ErrorHandler it never restores.
        restore_exception_handler();
    }

    public function testServiceReferenceStorageAliasesTheConfiguredFilesystem(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->assertSame(
            $container->get('custom.filesystem'),
            $container->get('bakslash_static_site.filesystem'),
        );
    }
}
