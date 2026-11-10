<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Functional;

use Bakslash\StaticSiteBundle\Tests\Fixtures\SiteHostKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class SiteHostGenerationTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return SiteHostKernel::class;
    }

    protected function tearDown(): void
    {
        if (self::$kernel instanceof SiteHostKernel) {
            (new Filesystem())->remove(self::$kernel->getOutputDir());
        }

        parent::tearDown();
    }

    public function testGeneratesEveryUriAgainstTheSiteContextDespiteTheResetBetweenPages(): void
    {
        $kernel = self::bootKernel();
        \assert($kernel instanceof SiteHostKernel);

        $tester = new CommandTester((new Application($kernel))->find('static-site:generate'));

        $this->assertSame(0, $tester->execute(['--allow-debug' => true]), $tester->getDisplay());

        $outputDir = $kernel->getOutputDir();
        $files = [];
        /** @var \SplFileInfo $file */
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($outputDir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $files[] = substr($file->getPathname(), \strlen($outputDir));
        }
        sort($files);

        $this->assertSame(['/first.html', '/second.html', '/third.html'], $files);
    }
}
