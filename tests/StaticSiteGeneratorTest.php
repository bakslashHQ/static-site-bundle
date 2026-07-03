<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests;

use Bakslash\StaticSiteBundle\Dumper\DumperInterface;
use Bakslash\StaticSiteBundle\Exception\RenderingException;
use Bakslash\StaticSiteBundle\Generator\StaticSiteGenerator;
use Bakslash\StaticSiteBundle\Renderer\RenderedPage;
use Bakslash\StaticSiteBundle\Renderer\RendererInterface;
use Bakslash\StaticSiteBundle\UriProvider\UriProviderInterface;
use PHPUnit\Framework\TestCase;

final class StaticSiteGeneratorTest extends TestCase
{
    public function testRendersAndDumpsEachUri(): void
    {
        $renderer = $this->createStub(RendererInterface::class);
        $renderer->method('render')->willReturn(new RenderedPage('content', 'html'));

        $dumper = $this->createMock(DumperInterface::class);
        $dumper->expects($this->exactly(2))->method('dump');

        $generator = new StaticSiteGenerator($this->uriProvider(['/a', '/b']), $renderer, $dumper);

        $results = iterator_to_array($generator->generate());

        $this->assertCount(2, $results);
        $this->assertSame('/a', $results[0]->uri);
        $this->assertTrue($results[0]->successful);
        $this->assertTrue($results[1]->successful);
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

        $generator = new StaticSiteGenerator($this->uriProvider(['/broken', '/ok']), $renderer, $dumper);

        $results = iterator_to_array($generator->generate());

        $this->assertFalse($results[0]->successful);
        $this->assertInstanceOf(RenderingException::class, $results[0]->error);
        $this->assertTrue($results[1]->successful);
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
