<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests;

/**
 * Drop this for PHPUnit's own expectExceptionMessageIs() once the lowest supported PHPUnit is 13.2.
 */
trait ExpectsExceptionMessage
{
    protected function expectExceptionMessageToBe(string $message): void
    {
        $this->expectExceptionMessageMatches('/\A'.preg_quote($message, '/').'\z/');
    }
}
