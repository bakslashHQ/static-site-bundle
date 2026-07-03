<?php

declare(strict_types=1);

namespace Bakslash\StaticSiteBundle\Command;

use Bakslash\StaticSiteBundle\Generator\StaticSiteGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
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

    public function __invoke(SymfonyStyle $io): int
    {
        $generated = 0;
        $failed = 0;
        foreach ($this->generator->generate() as $result) {
            if ($result->successful) {
                ++$generated;
                $io->writeln(\sprintf(' <info>✓</info> %s', $result->uri), OutputInterface::VERBOSITY_VERBOSE);

                continue;
            }

            ++$failed;
            $io->writeln(\sprintf(' <error>✗</error> %s: %s', $result->uri, $result->error?->getMessage()));
        }

        if ($failed > 0) {
            $io->error(\sprintf('Generated %d page(s), %d failed.', $generated, $failed));

            return Command::FAILURE;
        }

        $io->success(\sprintf('Generated %d page(s).', $generated));

        return Command::SUCCESS;
    }
}
