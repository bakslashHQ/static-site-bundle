<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Dumper;

use Bakslash\StaticSiteBundle\Exception\DumpingException;

interface DumperInterface
{
    /**
     * @throws DumpingException
     */
    public function dump(string $uri, string $content, ?string $format = null): void;

    /**
     * Removes everything previously dumped.
     *
     * @throws DumpingException
     */
    public function purge(): void;
}
