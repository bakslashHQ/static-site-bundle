<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Functional;

use Bakslash\StaticSiteBundle\Tests\Fixtures\HeadersFileKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class HeadersFileGenerationTest extends TestCase
{
    private ?HeadersFileKernel $kernel = null;

    protected function tearDown(): void
    {
        if (null !== $this->kernel) {
            (new Filesystem())->remove($this->kernel->getOutputDir());
            $this->kernel->shutdown();
        }
    }

    public function testWritesTheHeadersOfThePagesToTheHeadersFile(): void
    {
        $kernel = $this->kernel = new HeadersFileKernel('test', true);
        $this->generate($kernel);

        // X-Robots-Tag comes from FrameworkBundle: a debug kernel (--allow-debug here) asks
        // not to be indexed, and the headers file says what the application sent.
        $this->assertSame(implode("\n", [
            '# Written by static-site:generate. Edit outside these lines.',
            '/*',
            "  Content-Security-Policy: default-src 'self'",
            '  X-Robots-Tag: noindex',
            '/secured',
            '  Strict-Transport-Security: max-age=31536000; includeSubDomains',
            '# /plain (the /* rule only)',
            '# End of static-site:generate.',
            '',
        ]), (string) file_get_contents($kernel->getOutputDir().'/_headers'));
    }

    public function testWritesNoHeadersFileByDefault(): void
    {
        $kernel = $this->kernel = new HeadersFileKernel('test', true, null);
        $this->generate($kernel);

        $this->assertFileExists($kernel->getOutputDir().'/secured.html');
        $this->assertFileDoesNotExist($kernel->getOutputDir().'/_headers');
    }

    public function testRejectsAHeadersFileOutsideTheOutput(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/headers file must be a path inside the output/');

        (new HeadersFileKernel('test', true, '../_headers'))->boot();
    }

    private function generate(HeadersFileKernel $kernel): void
    {
        $kernel->boot();
        $tester = new CommandTester((new Application($kernel))->find('static-site:generate'));

        $this->assertSame(0, $tester->execute(['--allow-debug' => true]), $tester->getDisplay());
    }
}
