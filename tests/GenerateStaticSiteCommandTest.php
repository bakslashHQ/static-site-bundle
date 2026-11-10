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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\Service\ResetInterface;

final class GenerateStaticSiteCommandTest extends TestCase
{
    public function testReturnsSuccessWhenEveryPageSucceeds(): void
    {
        $renderer = $this->createStub(RendererInterface::class);
        $renderer->method('render')->willReturn(new RenderedPage('content', 'html'));

        $generator = new StaticSiteGenerator($this->uriProvider(['/a', '/b']), $renderer, $this->createStub(DumperInterface::class), new EventDispatcher(), $this->createStub(ResetInterface::class));

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

        $generator = new StaticSiteGenerator($this->uriProvider(['/ok', '/bad']), $renderer, $this->createStub(DumperInterface::class), new EventDispatcher(), $this->createStub(ResetInterface::class));

        [$exit, $display] = $this->invoke($generator);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('/bad: boom', $display);
        $this->assertStringContainsString('1 failed', $display);
    }

    public function testRefusesToGenerateFromADebugKernel(): void
    {
        $dumper = $this->createMock(DumperInterface::class);
        $dumper->expects($this->never())->method('dump');

        $generator = new StaticSiteGenerator($this->uriProvider(['/a']), $this->createStub(RendererInterface::class), $dumper, new EventDispatcher(), $this->createStub(ResetInterface::class));

        $output = new BufferedOutput();
        $exit = (new GenerateStaticSiteCommand($generator, debug: true))->__invoke(new SymfonyStyle(new ArrayInput([]), $output));

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('debug mode', $output->fetch());
    }

    public function testGeneratesFromADebugKernelWhenExplicitlyAllowed(): void
    {
        $renderer = $this->createStub(RendererInterface::class);
        $renderer->method('render')->willReturn(new RenderedPage('content', 'html'));

        $generator = new StaticSiteGenerator($this->uriProvider(['/a']), $renderer, $this->createStub(DumperInterface::class), new EventDispatcher(), $this->createStub(ResetInterface::class));

        $output = new BufferedOutput();
        $exit = (new GenerateStaticSiteCommand($generator, debug: true))->__invoke(new SymfonyStyle(new ArrayInput([]), $output), allowDebug: true);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Generated 1 page(s).', $output->fetch());
    }

    public function testWarnsWhenNothingWasGenerated(): void
    {
        $generator = new StaticSiteGenerator($this->uriProvider([]), $this->createStub(RendererInterface::class), $this->createStub(DumperInterface::class), new EventDispatcher(), $this->createStub(ResetInterface::class));

        [$exit, $display] = $this->invoke($generator);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('No page to generate', $display);
    }

    public function testFinishesTheProgressBarWhenTheRunAborts(): void
    {
        $provider = $this->createStub(UriProviderInterface::class);
        $provider->method('provide')->willReturn(['/a', '/b', '/c']);

        // Only rendering and dumping errors fail a single page; anything else aborts the run.
        $renderer = $this->createStub(RendererInterface::class);
        $renderer->method('render')->willReturnCallback(static fn (string $uri): RenderedPage => '/c' === $uri ? throw new \LogicException('boom') : new RenderedPage('content', 'html'));

        $generator = new StaticSiteGenerator($provider, $renderer, $this->createStub(DumperInterface::class), new EventDispatcher(), $this->createStub(ResetInterface::class));

        $output = new BufferedOutput(decorated: true);

        try {
            (new GenerateStaticSiteCommand($generator, debug: false))->__invoke(new SymfonyStyle(new ArrayInput([]), $output));
            $this->fail('Expected the error to propagate.');
        } catch (\LogicException) {
        }

        // Finished on the way out, so the counter is not left stale at its starting value.
        $this->assertStringContainsString('2 pages generated', (string) preg_replace('/\e\[[\d;]*m/', '', $output->fetch()));
    }

    /**
     * @param list<string> $uris
     */
    #[DataProvider('resolvedUrisProvider')]
    public function testShowsTheResolutionThenTheCounterInTheProgressBar(array $uris, string $counter): void
    {
        $provider = $this->createStub(UriProviderInterface::class);
        $provider->method('provide')->willReturn($uris);

        $renderer = $this->createStub(RendererInterface::class);
        $renderer->method('render')->willReturn(new RenderedPage('content', 'html'));

        $generator = new StaticSiteGenerator($provider, $renderer, $this->createStub(DumperInterface::class), new EventDispatcher(), $this->createStub(ResetInterface::class));

        $output = new BufferedOutput(decorated: true);
        (new GenerateStaticSiteCommand($generator, debug: false))->__invoke(new SymfonyStyle(new ArrayInput([]), $output));

        $display = (string) preg_replace('/\e\[[\d;]*m/', '', $output->fetch());
        $resolving = strpos($display, 'Resolving the pages to generate...');

        $this->assertNotFalse($resolving);
        // Even with nothing generated, the resolution message gives way to the counter.
        $this->assertGreaterThan($resolving, strrpos($display, $counter));
        $this->assertSame(1, substr_count($display, 'Resolving the pages to generate...'));
    }

    public function testRedrawsAFirstFailureWithTheCounter(): void
    {
        $provider = $this->createStub(UriProviderInterface::class);
        $provider->method('provide')->willReturn(['/broken']);

        $renderer = $this->createStub(RendererInterface::class);
        $renderer->method('render')->willThrowException(new RenderingException('boom'));

        $generator = new StaticSiteGenerator($provider, $renderer, $this->createStub(DumperInterface::class), new EventDispatcher(), $this->createStub(ResetInterface::class));

        $output = new BufferedOutput(decorated: true);
        (new GenerateStaticSiteCommand($generator, debug: false))->__invoke(new SymfonyStyle(new ArrayInput([]), $output));

        $display = (string) preg_replace('/\e\[[\d;]*m/', '', $output->fetch());
        $failure = strpos($display, '✗ /broken');

        // Redrawn right after the failure line, which the throttled finish() alone cannot prove.
        $this->assertNotFalse($failure);
        $this->assertStringContainsString('0 pages generated', substr($display, $failure));
        $this->assertStringNotContainsString('Resolving the pages to generate...', substr($display, $failure));
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function resolvedUrisProvider(): iterable
    {
        yield 'pages' => [['/a', '/b'], '2 pages generated'];
        yield 'no page' => [[], '0 pages generated'];
    }

    /**
     * @return array{int, string}
     */
    private function invoke(StaticSiteGenerator $generator): array
    {
        $output = new BufferedOutput();
        $exit = (new GenerateStaticSiteCommand($generator, debug: false))->__invoke(new SymfonyStyle(new ArrayInput([]), $output));

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
