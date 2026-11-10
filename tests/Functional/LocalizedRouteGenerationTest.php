<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Functional;

use Bakslash\StaticSiteBundle\Tests\Fixtures\LocalizedRouteKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class LocalizedRouteGenerationTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return LocalizedRouteKernel::class;
    }

    protected function tearDown(): void
    {
        if (self::$kernel instanceof LocalizedRouteKernel) {
            (new Filesystem())->remove(self::$kernel->getOutputDir());
        }

        parent::tearDown();
    }

    public function testRunsTheParamsProviderInTheLocaleOfEachLocalizedRoute(): void
    {
        $kernel = self::bootKernel();
        \assert($kernel instanceof LocalizedRouteKernel);

        $tester = new CommandTester((new Application($kernel))->find('static-site:generate'));

        $this->assertSame(0, $tester->execute(['--allow-debug' => true]), $tester->getDisplay());

        $outputDir = $kernel->getOutputDir();
        $this->assertStringContainsString('<html lang="en"><body>Article hello', (string) file_get_contents($outputDir.'/articles/hello.html'));
        $this->assertStringContainsString('<html lang="fr"><body>Article bonjour', (string) file_get_contents($outputDir.'/fr/articles/bonjour.html'));
        $this->assertFileDoesNotExist($outputDir.'/articles/bonjour.html');
        $this->assertFileDoesNotExist($outputDir.'/fr/articles/hello.html');

        // The locale is back to the default once the localized pages are generated.
        $this->assertStringContainsString('Locale en', (string) file_get_contents($outputDir.'/locale.html'));
    }
}
