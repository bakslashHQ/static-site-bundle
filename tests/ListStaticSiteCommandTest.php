<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests;

use Bakslash\StaticSiteBundle\Command\ListStaticSiteCommand;
use Bakslash\StaticSiteBundle\UriProvider\UriGenerationFailure;
use Bakslash\StaticSiteBundle\UriProvider\UriProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ListStaticSiteCommandTest extends TestCase
{
    public function testListsUniqueUris(): void
    {
        $provider = $this->createStub(UriProviderInterface::class);
        $provider->method('provide')->willReturn(['/a', '/b', '/a']);

        [$exit, $display] = $this->invoke($provider);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('/a', $display);
        $this->assertStringContainsString('/b', $display);
        $this->assertStringContainsString('2 URI(s).', $display);
    }

    public function testReportsFailuresAndReturnsFailure(): void
    {
        $provider = $this->createStub(UriProviderInterface::class);
        $provider->method('provide')->willReturn(['/a', new UriGenerationFailure('route "x"', new \RuntimeException('bad'))]);

        [$exit, $display] = $this->invoke($provider);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('route "x": bad', $display);
        $this->assertStringContainsString('1 could not be built', $display);
    }

    public function testWarnsWhenThereIsNothingToList(): void
    {
        $provider = $this->createStub(UriProviderInterface::class);
        $provider->method('provide')->willReturn([]);

        [$exit, $display] = $this->invoke($provider);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('No URI to list', $display);
    }

    /**
     * @return array{int, string}
     */
    private function invoke(UriProviderInterface $provider): array
    {
        $output = new BufferedOutput();
        $exit = (new ListStaticSiteCommand($provider))->__invoke(new SymfonyStyle(new ArrayInput([]), $output));

        return [$exit, $output->fetch()];
    }
}
