<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'ssg:generate', description: 'Generates static pages')]
final class GenerateStaticSiteCommand extends Command
{
    public function __construct(
        private readonly StaticPagesGenerator $staticPagesGenerator,
        private readonly StaticPageDumperInterface $staticPageDumper,
        private readonly StaticPageUrisProviderInterface $staticPageUrisProvider,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'A regex pattern to filter the pages to generate')]
        ?string $filterPattern = null,
        #[Option(description: 'Do not dump pages')]
        bool $dryRun = false,
    ): int {
        if ($filterPattern !== null && @preg_match($filterPattern, '') === false) {
            $io->error(\sprintf('Invalid regex pattern: "%s"', $filterPattern));

            return Command::FAILURE;
        }

        $successful = true;

        foreach ($this->staticPageUrisProvider->provide() as $uri) {
            if ($filterPattern !== null && in_array(preg_match($filterPattern, $uri), [0, false], true)) {
                continue;
            }

            try {
                ['content' => $content, 'format' => $format] = $this->staticPagesGenerator->generate($uri);

                if (!$dryRun) {
                    $this->staticPageDumper->dump($uri, $content, $format);
                }

                $io->info(\sprintf('Generated static page for URI "%s"', $uri));
            } catch (\RuntimeException $exception) {
                $io->error(\sprintf('Generating page for URI "%s" failed: %s', $uri, $exception->getMessage()));
                $successful = false;
            }
        }

        return $successful ? Command::SUCCESS : Command::FAILURE;
    }
}
