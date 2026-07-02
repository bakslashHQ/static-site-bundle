<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests;

use Bakslash\StaticSiteBundle\Renderer\MinifyingRenderer;
use Bakslash\StaticSiteBundle\Renderer\RenderedPage;
use Bakslash\StaticSiteBundle\Renderer\RendererInterface;
use PHPUnit\Framework\TestCase;
use Sensiolabs\MinifyBundle\Minifier\MinifierInterface;

final class MinifyingRendererTest extends TestCase
{
    public function testMinifiesHtml(): void
    {
        $inner = $this->createStub(RendererInterface::class);
        $inner->method('render')->willReturn(new RenderedPage('<html></html>', 'html'));

        $minify = $this->createMock(MinifierInterface::class);
        $minify->expects($this->once())->method('minify')->with('<html></html>', 'html')->willReturn('<html>min</html>');

        $page = (new MinifyingRenderer($inner, $minify))->render('/page');

        $this->assertSame('<html>min</html>', $page->content);
        $this->assertSame('html', $page->format);
    }

    public function testMinifiesXml(): void
    {
        $inner = $this->createStub(RendererInterface::class);
        $inner->method('render')->willReturn(new RenderedPage('<xml/>', 'xml'));

        $minify = $this->createMock(MinifierInterface::class);
        $minify->expects($this->once())->method('minify')->with('<xml/>', 'xml')->willReturn('<xml/>');

        $page = (new MinifyingRenderer($inner, $minify))->render('/sitemap.xml');

        $this->assertSame('xml', $page->format);
    }

    public function testDoesNotMinifyOtherFormats(): void
    {
        $inner = $this->createStub(RendererInterface::class);
        $inner->method('render')->willReturn(new RenderedPage('{}', 'json'));

        $minify = $this->createMock(MinifierInterface::class);
        $minify->expects($this->never())->method('minify');

        $page = (new MinifyingRenderer($inner, $minify))->render('/data.json');

        $this->assertSame('{}', $page->content);
    }
}
