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
use Symfony\Component\EventDispatcher\EventDispatcher;

final class GenerateStaticSiteCommandTest extends TestCase
{
    public function testReturnsSuccessWhenEveryPageSucceeds(): void
    {
        $renderer = $this->createStub(RendererInterface::class);
        $renderer->method('render')->willReturn(new RenderedPage('content', 'html'));

        $generator = new StaticSiteGenerator($this->uriProvider(['/a', '/b']), $renderer, $this->createStub(DumperInterface::class), new EventDispatcher());

        [$exit, $display] = $this->invoke($generator);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Generated 2 page(s).', $display);
    }

    public function testReportsFailuresAndReturnsFailure(): void
    {
        $renderer = $this->createStub(RendererInterface::class);
        $renderer->method('render')->willReturnCallback(static function (string $uri): RenderedPage {
            if ('/bad' === $uri) {
                throw new RenderingException('boom');
            }

            return new RenderedPage('content', 'html');
        });

        $generator = new StaticSiteGenerator($this->uriProvider(['/ok', '/bad']), $renderer, $this->createStub(DumperInterface::class), new EventDispatcher());

        [$exit, $display] = $this->invoke($generator);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('/bad: boom', $display);
        $this->assertStringContainsString('1 failed', $display);
    }

    /**
     * @return array{int, string}
     */
    private function invoke(StaticSiteGenerator $generator): array
    {
        $output = new BufferedOutput();
        $exit = (new GenerateStaticSiteCommand($generator))->__invoke(new SymfonyStyle(new ArrayInput([]), $output));

        return [$exit, $output->fetch()];
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
