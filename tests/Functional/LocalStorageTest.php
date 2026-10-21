<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Functional;

use Bakslash\StaticSiteBundle\Tests\Fixtures\LocalStorageKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class LocalStorageTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return LocalStorageKernel::class;
    }

    protected function tearDown(): void
    {
        if (self::$kernel instanceof LocalStorageKernel) {
            (new Filesystem())->remove(self::$kernel->getOutputDir());
        }

        parent::tearDown();
    }

    public function testGeneratedDirectoriesAreReadableByOtherUsers(): void
    {
        $this->assertDirectoryModes('0755', $this->generate([]));
    }

    public function testDirectoryModesFollowARedefinedVisibilityService(): void
    {
        $this->assertDirectoryModes('0750', $this->generate(['environment' => LocalStorageKernel::CUSTOM_VISIBILITY_ENV]));
    }

    /**
     * @param array{environment?: string} $options
     */
    private function generate(array $options): string
    {
        $umask = umask(0o022);

        try {
            $kernel = self::bootKernel($options);
            \assert($kernel instanceof LocalStorageKernel);

            $tester = new CommandTester((new Application($kernel))->find('static-site:generate'));

            $this->assertSame(0, $tester->execute(['--allow-debug' => true]), $tester->getDisplay());
        } finally {
            umask($umask);
        }

        return $kernel->getOutputDir();
    }

    private function assertDirectoryModes(string $expected, string $outputDir): void
    {
        foreach ([$outputDir, $outputDir.'/articles', $outputDir.'/articles/foo'] as $directory) {
            $this->assertSame($expected, \sprintf('%04o', fileperms($directory) & 0o777), $directory);
        }
    }
}
