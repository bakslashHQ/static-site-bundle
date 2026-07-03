<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests;

use Bakslash\StaticSiteBundle\Event\PageRenderedEvent;
use Bakslash\StaticSiteBundle\EventListener\MinifyListener;
use Bakslash\StaticSiteBundle\Renderer\RenderedPage;
use PHPUnit\Framework\TestCase;
use Sensiolabs\MinifyBundle\Minifier\MinifierInterface;

final class MinifyListenerTest extends TestCase
{
    public function testMinifiesHtml(): void
    {
        $minify = $this->createMock(MinifierInterface::class);
        $minify->expects($this->once())->method('minify')->with('<html></html>', 'html')->willReturn('<html>min</html>');

        $event = new PageRenderedEvent('/page', new RenderedPage('<html></html>', 'html'));
        (new MinifyListener($minify))($event);

        $this->assertSame('<html>min</html>', $event->getPage()->content);
        $this->assertSame('html', $event->getPage()->format);
    }

    public function testMinifiesXml(): void
    {
        $minify = $this->createMock(MinifierInterface::class);
        $minify->expects($this->once())->method('minify')->with('<xml/>', 'xml')->willReturn('<xml/>');

        $event = new PageRenderedEvent('/sitemap.xml', new RenderedPage('<xml/>', 'xml'));
        (new MinifyListener($minify))($event);

        $this->assertSame('xml', $event->getPage()->format);
    }

    public function testDoesNotMinifyOtherFormats(): void
    {
        $minify = $this->createMock(MinifierInterface::class);
        $minify->expects($this->never())->method('minify');

        $event = new PageRenderedEvent('/data.json', new RenderedPage('{}', 'json'));
        (new MinifyListener($minify))($event);

        $this->assertSame('{}', $event->getPage()->content);
    }
}
