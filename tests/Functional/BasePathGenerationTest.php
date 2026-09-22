<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Functional;

use Bakslash\StaticSiteBundle\Tests\Fixtures\BasePathKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RequestContext;

final class BasePathGenerationTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return BasePathKernel::class;
    }

    protected function tearDown(): void
    {
        if (self::$kernel instanceof BasePathKernel) {
            (new Filesystem())->remove(self::$kernel->getOutputDir());
        }

        parent::tearDown();
    }

    public function testGeneratesEveryPageUnderABaseUriPath(): void
    {
        $kernel = self::bootKernel();
        \assert($kernel instanceof BasePathKernel);

        $tester = new CommandTester((new Application($kernel))->find('static-site:generate'));
        $exitCode = $tester->execute(['--allow-debug' => true]);

        $this->assertSame(0, $exitCode, $tester->getDisplay());
        $this->assertStringContainsString('Generated 3 page(s).', $tester->getDisplay());

        // Written relative to the base path, not under a "docs/" directory.
        $outputDir = $kernel->getOutputDir();
        foreach (['one', 'two', 'three'] as $page) {
            $this->assertFileExists($outputDir.'/'.$page.'.html');
        }
        $this->assertDirectoryDoesNotExist($outputDir.'/docs');
    }

    public function testListsEveryUriRelativeToTheBasePath(): void
    {
        $kernel = self::bootKernel();

        $tester = new CommandTester((new Application($kernel))->find('static-site:list'));

        $this->assertSame(0, $tester->execute([]), $tester->getDisplay());

        $display = $tester->getDisplay();
        foreach (['/one', '/two', '/three'] as $uri) {
            $this->assertMatchesRegularExpression('/^'.preg_quote($uri, '/').'$/m', $display);
        }
    }

    public function testLeavesTheRouterRequestContextUntouched(): void
    {
        $kernel = self::bootKernel();
        $context = self::getContainer()->get('router.request_context');
        \assert($context instanceof RequestContext);

        // The app's own default URI, which generation must not be allowed to overwrite.
        $this->assertSame(['http', 'localhost', ''], [$context->getScheme(), $context->getHost(), $context->getBaseUrl()]);

        (new CommandTester((new Application($kernel))->find('static-site:generate')))->execute(['--allow-debug' => true]);

        // Generating twice in one process must stay correct.
        $this->assertSame(['http', 'localhost', ''], [$context->getScheme(), $context->getHost(), $context->getBaseUrl()]);
    }
}
