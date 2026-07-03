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
    ): int {
        $filter = [] === $route && [] === $path ? null : new GenerationFilter($route, $path);

        $generated = 0;
        $failed = 0;

        // A live counter for long runs, shown only for an interactive, non-verbose run: with
        // "-v" the per-page lines are the progress, and on a non-TTY (CI) a bar would spam logs.
        $progress = !$io->isVerbose() && $io->isDecorated() ? $io->createProgressBar() : null;
        $progress?->setFormat(' <info>%current%</info> pages generated (%elapsed%)');
        $progress?->start();

        foreach ($this->generator->generate($filter) as $result) {
            if ($result->successful) {
                ++$generated;
                $io->writeln(\sprintf(' <info>✓</info> %s', $result->uri), OutputInterface::VERBOSITY_VERBOSE);
            } else {
                ++$failed;
                $progress?->clear();
                $io->writeln(\sprintf(' <error>✗</error> %s: %s', $result->uri, $result->error?->getMessage()));
                $progress?->display();
            }

            $progress?->advance();
        }

        $progress?->finish();
        if (null !== $progress) {
            $io->newLine(2);
        }

        if ($failed > 0) {
            $io->error(\sprintf('Generated %d page(s), %d failed.', $generated, $failed));

            return Command::FAILURE;
        }

        $io->success(\sprintf('Generated %d page(s).', $generated));

        return Command::SUCCESS;
    }
}
