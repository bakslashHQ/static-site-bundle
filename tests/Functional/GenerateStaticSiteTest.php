<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Functional;

use Bakslash\StaticSiteBundle\Tests\Fixtures\GenerationKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class GenerateStaticSiteTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return GenerationKernel::class;
    }

    protected function tearDown(): void
    {
        if (self::$kernel instanceof GenerationKernel) {
            (new Filesystem())->remove(self::$kernel->getOutputDir());
        }

        parent::tearDown();
    }

    public function testGeneratesMinifiedPageEndToEnd(): void
    {
        $kernel = self::bootKernel();
        \assert($kernel instanceof GenerationKernel);

        $tester = new CommandTester((new Application($kernel))->find('static-site:generate'));
        $exitCode = $tester->execute(['--allow-debug' => true]);

        $this->assertSame(0, $exitCode, $tester->getDisplay());

        $file = $kernel->getOutputDir().'/hello.html';
        $this->assertFileExists($file);

        $content = (string) file_get_contents($file);
        $this->assertStringStartsWith('<!--minified-->', $content, 'page should be minified');
        $this->assertStringContainsString('Hello', $content, 'rendered content should be preserved');
    }

    public function testGeneratesARouteMarkedInTheRoutingConfiguration(): void
    {
        $kernel = self::bootKernel();
        \assert($kernel instanceof GenerationKernel);

        $tester = new CommandTester((new Application($kernel))->find('static-site:generate'));

        $this->assertSame(0, $tester->execute(['--allow-debug' => true]), $tester->getDisplay());
        $this->assertStringContainsString('Configured', (string) file_get_contents($kernel->getOutputDir().'/configured.html'));
    }
}
