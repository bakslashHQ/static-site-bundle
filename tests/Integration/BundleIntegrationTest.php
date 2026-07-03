<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Integration;

use Bakslash\StaticSiteBundle\Tests\Fixtures\TestKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;

final class BundleIntegrationTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // FrameworkBundle::boot() registers an ErrorHandler as the exception handler
        // but does not restore it on shutdown; clean it up so the test is not flagged risky.
        restore_exception_handler();
    }

    public function testGenerateCommandIsWired(): void
    {
        $kernel = self::bootKernel();

        $command = (new Application($kernel))->find('static-site:generate');
        if ($command instanceof LazyCommand) {
            // Resolving the lazy command instantiates it and all of its dependencies.
            $command = $command->getCommand();
        }

        $this->assertInstanceOf(Command::class, $command);
        $this->assertSame('static-site:generate', $command->getName());
    }

    public function testMinifyListenerIsNotRegisteredWhenMinifyDisabled(): void
    {
        self::bootKernel();

        // Minification defaults to false, so MinifierPass must not register the listener.
        $this->assertFalse(self::getContainer()->has('.bakslash_static_site.minify_listener'));
    }
}
