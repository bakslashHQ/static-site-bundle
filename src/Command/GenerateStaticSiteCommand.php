<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Command;

use Bakslash\StaticSiteBundle\Generator\StaticSiteGenerator;
use Bakslash\StaticSiteBundle\UriProvider\GenerationFilter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(name: 'static-site:generate', description: 'Generates static pages')]
final readonly class GenerateStaticSiteCommand
{
    public function __construct(
        private StaticSiteGenerator $generator,
        private bool $debug,
    ) {
    }

    /**
     * @param list<string> $route
     * @param list<string> $path
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Only generate routes whose name matches this glob (repeatable).')]
        array $route = [],
        #[Option(description: 'Only generate URIs matching this glob (repeatable).')]
        array $path = [],
        #[Option(description: 'Generate even though the kernel is in debug mode.')]
        bool $allowDebug = false,
    ): int {
        if ($this->debug && !$allowDebug) {
            $io->error('Refusing to generate in debug mode: pages would carry the web debug toolbar. Run with --env=prod or --allow-debug.');

            return Command::FAILURE;
        }

        $generated = 0;
        $failed = 0;

        $progress = !$io->isVerbose() && $io->isDecorated() ? $io->createProgressBar() : null;
        $progress?->setFormat(' <info>%current%</info> pages generated (%elapsed%)');
        $progress?->start();

        try {
            foreach ($this->generator->generate(new GenerationFilter($route, $path)) as $result) {
                if (null === $result->error) {
                    ++$generated;
                    $io->writeln(\sprintf(' <info>✓</info> %s', $result->uri), OutputInterface::VERBOSITY_VERBOSE);
                } else {
                    ++$failed;
                    $progress?->clear();
                    $io->writeln(\sprintf(' <error>✗</error> %s: %s', $result->uri, $result->error->getMessage()));
                    $progress?->display();
                }

                $progress?->advance();
            }
        } finally {
            if (null !== $progress) {
                $progress->finish();
                $io->newLine(2);
            }
        }

        if ($failed > 0) {
            $io->error(\sprintf('Generated %d page(s), %d failed.', $generated, $failed));

            return Command::FAILURE;
        }

        if (0 === $generated) {
            $io->warning('No page to generate: check the #[Prerender] attributes and any --route/--path filter.');

            return Command::SUCCESS;
        }

        $io->success(\sprintf('Generated %d page(s).', $generated));

        return Command::SUCCESS;
    }
}
