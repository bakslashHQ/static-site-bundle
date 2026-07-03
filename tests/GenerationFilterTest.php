<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests;

use Bakslash\StaticSiteBundle\UriProvider\GenerationFilter;
use PHPUnit\Framework\TestCase;

final class GenerationFilterTest extends TestCase
{
    public function testEmptyFilterMatchesEverything(): void
    {
        $filter = new GenerationFilter();

        $this->assertTrue($filter->matchesRoute('anything'));
        $this->assertTrue($filter->matchesPath('/anything'));
    }

    public function testMatchesRouteByGlob(): void
    {
        $filter = new GenerationFilter(routes: ['blog_*']);

        $this->assertTrue($filter->matchesRoute('blog_show'));
        $this->assertFalse($filter->matchesRoute('shop_index'));
    }

    public function testMatchesPathByGlob(): void
    {
        $filter = new GenerationFilter(paths: ['/blog/*']);

        $this->assertTrue($filter->matchesPath('/blog/hello'));
        $this->assertFalse($filter->matchesPath('/shop'));
    }

    public function testMatchesAnyOfSeveralPatterns(): void
    {
        $filter = new GenerationFilter(paths: ['/a', '/b']);

        $this->assertTrue($filter->matchesPath('/a'));
        $this->assertTrue($filter->matchesPath('/b'));
        $this->assertFalse($filter->matchesPath('/c'));
    }
}
