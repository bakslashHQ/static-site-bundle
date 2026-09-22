<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Command;

use Bakslash\StaticSiteBundle\UriProvider\GenerationFilter;
use Bakslash\StaticSiteBundle\UriProvider\UriGenerationFailure;
use Bakslash\StaticSiteBundle\UriProvider\UriProviderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(name: 'static-site:list', description: 'Lists the URIs that would be generated, without generating them')]
final readonly class ListStaticSiteCommand
{
    public function __construct(
        private UriProviderInterface $uriProvider,
    ) {
    }

    /**
     * @param list<string> $route
     * @param list<string> $path
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Only list routes whose name matches this glob (repeatable).')]
        array $route = [],
        #[Option(description: 'Only list URIs matching this glob (repeatable).')]
        array $path = [],
    ): int {
        $uris = [];
        $failed = 0;
        foreach ($this->uriProvider->provide(new GenerationFilter($route, $path)) as $item) {
            if ($item instanceof UriGenerationFailure) {
                ++$failed;
                $io->writeln(\sprintf(' <error>✗</error> %s: %s', $item->identifier, $item->error->getMessage()));

                continue;
            }

            $uris[$item] = true;
        }

        foreach (array_keys($uris) as $uri) {
            $io->writeln($uri);
        }

        if ($failed > 0) {
            $io->error(\sprintf('%d URI(s), %d could not be built.', \count($uris), $failed));

            return Command::FAILURE;
        }

        if ([] === $uris) {
            $io->warning('No URI to list: check the #[Prerender] attributes and any --route/--path filter.');

            return Command::SUCCESS;
        }

        $io->success(\sprintf('%d URI(s).', \count($uris)));

        return Command::SUCCESS;
    }
}
