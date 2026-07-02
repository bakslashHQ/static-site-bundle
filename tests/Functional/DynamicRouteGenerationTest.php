<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Functional;

use Bakslash\StaticSiteBundle\Tests\Fixtures\DynamicRouteKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class DynamicRouteGenerationTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return DynamicRouteKernel::class;
    }

    protected function tearDown(): void
    {
        if (self::$kernel instanceof DynamicRouteKernel) {
            (new Filesystem())->remove(self::$kernel->getOutputDir());
        }

        parent::tearDown();

        // FrameworkBundle::boot() registers an ErrorHandler it never restores.
        restore_exception_handler();
    }

    public function testGeneratesOnePagePerProvidedParameterSet(): void
    {
        $kernel = self::bootKernel();
        \assert($kernel instanceof DynamicRouteKernel);

        $tester = new CommandTester((new Application($kernel))->find('static-site:generate'));
        $exitCode = $tester->execute([]);

        $this->assertSame(0, $exitCode, $tester->getDisplay());

        $outputDir = $kernel->getOutputDir();
        $this->assertFileExists($outputDir.'/articles/foo.html');
        $this->assertFileExists($outputDir.'/articles/bar.html');
        $this->assertStringContainsString('Article foo', (string) file_get_contents($outputDir.'/articles/foo.html'));
        $this->assertStringContainsString('Article bar', (string) file_get_contents($outputDir.'/articles/bar.html'));
    }
}
