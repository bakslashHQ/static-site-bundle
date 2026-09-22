<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests;

use Bakslash\StaticSiteBundle\Dumper\DumperInterface;
use Bakslash\StaticSiteBundle\Event\PageDumpedEvent;
use Bakslash\StaticSiteBundle\Event\PageFailedEvent;
use Bakslash\StaticSiteBundle\Event\PageRenderedEvent;
use Bakslash\StaticSiteBundle\Exception\RenderingException;
use Bakslash\StaticSiteBundle\Generator\StaticSiteGenerator;
use Bakslash\StaticSiteBundle\Renderer\RenderedPage;
use Bakslash\StaticSiteBundle\Renderer\RendererInterface;
use Bakslash\StaticSiteBundle\UriProvider\UriGenerationFailure;
use Bakslash\StaticSiteBundle\UriProvider\UriProviderInterface;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\Service\ResetInterface;

final class StaticSiteGeneratorTest extends TestCase
{
    public function testRendersAndDumpsEachUri(): void
    {
        $renderer = $this->createStub(RendererInterface::class);
        $renderer->method('render')->willReturn(new RenderedPage('content', 'html'));

        $dumper = $this->createMock(DumperInterface::class);
        $dumper->expects($this->exactly(2))->method('dump');

        $results = iterator_to_array($this->generator($this->uriProvider(['/a', '/b']), $renderer, $dumper)->generate());

        $this->assertCount(2, $results);
        $this->assertSame('/a', $results[0]->uri);
        $this->assertTrue($results[0]->successful);
        $this->assertTrue($results[1]->successful);
    }

    public function testGeneratesEachUriOnlyOnce(): void
    {
        $renderer = $this->createStub(RendererInterface::class);
        $renderer->method('render')->willReturn(new RenderedPage('content', 'html'));

        $dumper = $this->createMock(DumperInterface::class);
        $dumper->expects($this->once())->method('dump');

        $results = iterator_to_array($this->generator($this->uriProvider(['/a', '/a']), $renderer, $dumper)->generate());

        $this->assertCount(1, $results);
        $this->assertSame('/a', $results[0]->uri);
    }

    public function testRenderFailureYieldsFailureResultAndKeepsGoing(): void
    {
        $renderer = $this->createStub(RendererInterface::class);
        $renderer->method('render')->willReturnCallback(static function (string $uri): RenderedPage {
            if ('/broken' === $uri) {
                throw new RenderingException('boom');
            }

            return new RenderedPage('content', 'html');
        });

        $dumper = $this->createMock(DumperInterface::class);
        $dumper->expects($this->once())->method('dump'); // only the working page is dumped

        $results = iterator_to_array($this->generator($this->uriProvider(['/broken', '/ok']), $renderer, $dumper)->generate());

        $this->assertFalse($results[0]->successful);
        $this->assertInstanceOf(RenderingException::class, $results[0]->error);
        $this->assertTrue($results[1]->successful);
    }

    public function testUriGenerationFailureBecomesAFailureResult(): void
    {
        $renderer = $this->createStub(RendererInterface::class);
        $renderer->method('render')->willReturn(new RenderedPage('content', 'html'));

        $failure = new UriGenerationFailure('route "article"', new \RuntimeException('bad slug'));
        $provider = $this->createStub(UriProviderInterface::class);
        $provider->method('provide')->willReturn(['/a', $failure, '/b']);

        $results = iterator_to_array($this->generator($provider, $renderer, $this->createStub(DumperInterface::class))->generate());

        $this->assertTrue($results[0]->successful);
        $this->assertFalse($results[1]->successful);
        $this->assertSame('route "article"', $results[1]->uri);
        $this->assertSame($failure->error, $results[1]->error);
        $this->assertTrue($results[2]->successful);
    }

    public function testPageRenderedEventCanTransformTheContent(): void
    {
        $renderer = $this->createStub(RendererInterface::class);
        $renderer->method('render')->willReturn(new RenderedPage('original', 'html'));

        $dumper = $this->createMock(DumperInterface::class);
        $dumper->expects($this->once())->method('dump')->with('/a', new RenderedPage('transformed', 'html'));

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(PageRenderedEvent::class, static function (PageRenderedEvent $event): void {
            $event->setPage(new RenderedPage('transformed', $event->getPage()->format));
        });

        iterator_to_array($this->generator($this->uriProvider(['/a']), $renderer, $dumper, $dispatcher)->generate());
    }

    public function testDispatchesPageDumpedEventForEachSuccess(): void
    {
        $renderer = $this->createStub(RendererInterface::class);
        $renderer->method('render')->willReturn(new RenderedPage('content', 'html'));

        $dumped = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(PageDumpedEvent::class, static function (PageDumpedEvent $event) use (&$dumped): void {
            $dumped[] = $event->uri;
        });

        iterator_to_array($this->generator($this->uriProvider(['/a', '/b']), $renderer, $this->createStub(DumperInterface::class), $dispatcher)->generate());

        $this->assertSame(['/a', '/b'], $dumped);
    }

    public function testDispatchesPageFailedEventForRenderAndUriFailures(): void
    {
        $renderer = $this->createStub(RendererInterface::class);
        $renderer->method('render')->willReturnCallback(static function (string $uri): RenderedPage {
            if ('/broken' === $uri) {
                throw new RenderingException('boom');
            }

            return new RenderedPage('content', 'html');
        });

        $failed = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(PageFailedEvent::class, static function (PageFailedEvent $event) use (&$failed): void {
            $failed[] = $event->identifier;
        });

        $provider = $this->createStub(UriProviderInterface::class);
        $provider->method('provide')->willReturn(['/broken', new UriGenerationFailure('route "x"', new \RuntimeException('bad'))]);

        iterator_to_array($this->generator($provider, $renderer, $this->createStub(DumperInterface::class), $dispatcher)->generate());

        $this->assertSame(['/broken', 'route "x"'], $failed);
    }

    public function testResetsServicesAfterEachPage(): void
    {
        $renderer = $this->createStub(RendererInterface::class);
        $renderer->method('render')->willReturn(new RenderedPage('content', 'html'));

        $servicesResetter = $this->createMock(ResetInterface::class);
        $servicesResetter->expects($this->exactly(2))->method('reset');

        iterator_to_array($this->generator($this->uriProvider(['/a', '/b']), $renderer, $this->createStub(DumperInterface::class), servicesResetter: $servicesResetter)->generate());
    }

    public function testResetsServicesAfterAFailedPage(): void
    {
        $renderer = $this->createStub(RendererInterface::class);
        $renderer->method('render')->willThrowException(new RenderingException('boom'));

        $servicesResetter = $this->createMock(ResetInterface::class);
        $servicesResetter->expects($this->once())->method('reset');

        iterator_to_array($this->generator($this->uriProvider(['/broken']), $renderer, $this->createStub(DumperInterface::class), servicesResetter: $servicesResetter)->generate());
    }

    private function generator(
        UriProviderInterface $uriProvider,
        RendererInterface $renderer,
        DumperInterface $dumper,
        ?EventDispatcherInterface $dispatcher = null,
        ?ResetInterface $servicesResetter = null,
    ): StaticSiteGenerator {
        return new StaticSiteGenerator(
            $uriProvider,
            $renderer,
            $dumper,
            $dispatcher ?? new EventDispatcher(),
            $servicesResetter ?? $this->createStub(ResetInterface::class),
        );
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
