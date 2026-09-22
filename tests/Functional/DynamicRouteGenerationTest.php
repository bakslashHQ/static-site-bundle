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
    }

    public function testGeneratesOnePagePerProvidedParameterSet(): void
    {
        $kernel = self::bootKernel();
        \assert($kernel instanceof DynamicRouteKernel);

        $tester = new CommandTester((new Application($kernel))->find('static-site:generate'));
        $exitCode = $tester->execute([]);

        $this->assertSame(0, $exitCode, $tester->getDisplay());

        // clean_urls is enabled, so pages are written as "<path>/index.html".
        $outputDir = $kernel->getOutputDir();
        $this->assertFileExists($outputDir.'/articles/foo/index.html');
        $this->assertFileExists($outputDir.'/articles/bar/index.html');
        $this->assertStringContainsString('Article foo', (string) file_get_contents($outputDir.'/articles/foo/index.html'));
        $this->assertStringContainsString('Article bar', (string) file_get_contents($outputDir.'/articles/bar/index.html'));
    }

    public function testThePathOptionRestrictsWhatIsGenerated(): void
    {
        $kernel = self::bootKernel();
        \assert($kernel instanceof DynamicRouteKernel);

        $tester = new CommandTester((new Application($kernel))->find('static-site:generate'));

        $this->assertSame(0, $tester->execute(['--path' => ['/articles/foo']]), $tester->getDisplay());

        $outputDir = $kernel->getOutputDir();
        $this->assertFileExists($outputDir.'/articles/foo/index.html');
        $this->assertFileDoesNotExist($outputDir.'/articles/bar/index.html');
    }

    public function testTheRouteOptionRestrictsWhatIsGenerated(): void
    {
        $kernel = self::bootKernel();
        \assert($kernel instanceof DynamicRouteKernel);

        $tester = new CommandTester((new Application($kernel))->find('static-site:generate'));

        $this->assertSame(0, $tester->execute(['--route' => ['other_*']]), $tester->getDisplay());

        $this->assertDirectoryDoesNotExist($kernel->getOutputDir().'/articles');
    }
}
