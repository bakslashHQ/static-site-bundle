<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Integration;

use Bakslash\StaticSiteBundle\Tests\Fixtures\InvalidBaseUriKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

final class BaseUriConfigurationTest extends TestCase
{
    public function testRejectsBaseUriWithoutScheme(): void
    {
        $kernel = new InvalidBaseUriKernel('test', true);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/base_uri.*absolute URI including a scheme and host/');

        $kernel->boot();
    }
}
