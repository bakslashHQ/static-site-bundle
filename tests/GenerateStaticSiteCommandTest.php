<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests;

use Bakslash\StaticSiteBundle\Command\GenerateStaticSiteCommand;
use Bakslash\StaticSiteBundle\Dumper\DumperInterface;
use Bakslash\StaticSiteBundle\Exception\RenderingException;
use Bakslash\StaticSiteBundle\Generator\StaticSiteGenerator;
use Bakslash\StaticSiteBundle\Renderer\RenderedPage;
use Bakslash\StaticSiteBundle\Renderer\RendererInterface;
use Bakslash\StaticSiteBundle\UriProvider\UriProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

final class GenerateStaticSiteCommandTest extends TestCase
{
    public function testReturnsSuccessWhenEveryPageSucceeds(): void
    {
        $renderer = $this->createStub(RendererInterface::class);
        $renderer->method('render')->willReturn(new RenderedPage('content', 'html'));

        $generator = new StaticSiteGenerator($this->uriProvider(['/a']), $renderer, $this->createStub(DumperInterface::class));

        $this->assertSame(0, (new GenerateStaticSiteCommand($generator))->__invoke($this->io()));
    }

    public function testReturnsFailureWhenAPageFails(): void
    {
        $renderer = $this->createStub(RendererInterface::class);
        $renderer->method('render')->willThrowException(new RenderingException('boom'));

        $generator = new StaticSiteGenerator($this->uriProvider(['/a']), $renderer, $this->createStub(DumperInterface::class));

        $this->assertSame(1, (new GenerateStaticSiteCommand($generator))->__invoke($this->io()));
    }

    private function io(): SymfonyStyle
    {
        return new SymfonyStyle(new ArrayInput([]), new BufferedOutput());
    }

    /**
     * @param list<string> $uris
     */
    private function uriProvider(array $uris): UriProviderInterface
    {
        $provider = $this->createStub(UriProviderInterface::class);
        $provider->method('provide')->willReturn($uris);

        return $provider;
    }
}
