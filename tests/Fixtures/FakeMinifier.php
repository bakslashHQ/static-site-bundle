<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Tests\Fixtures;

use Sensiolabs\MinifyBundle\Minifier\MinifierInterface;

/**
 * Prefixes content with a marker so tests can assert minification actually ran.
 */
final class FakeMinifier implements MinifierInterface
{
    public function minify(string $input, string $type): string
    {
        return '<!--minified-->'.$input;
    }
}
