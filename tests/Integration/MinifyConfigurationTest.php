<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Integration;

use Bakslash\StaticSiteBundle\Exception\LogicException;
use Bakslash\StaticSiteBundle\Tests\Fixtures\MinifyKernel;
use PHPUnit\Framework\TestCase;

final class MinifyConfigurationTest extends TestCase
{
    public function testEnablingMinifyWithoutMinifierThrows(): void
    {
        $kernel = new MinifyKernel('test', true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('Package "sensiolabs/minify-bundle" is required to use the "minify" option. Try running "composer require sensiolabs/minify-bundle".');

        $kernel->boot();
    }
}
