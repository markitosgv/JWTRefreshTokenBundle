<?php

declare(strict_types=1);

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Command;

use DateTime;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'gesdinet:jwt:clear', description: 'Clear invalid refresh tokens.')]
final class ClearInvalidRefreshTokensCommand extends Command
{
    /**
     * @param positive-int $defaultBatchSize
     */
    public function __construct(
        private readonly RefreshTokenManagerInterface $refreshTokenManager,
        private readonly int $defaultBatchSize,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('datetime', InputArgument::OPTIONAL, 'An optional date, all tokens before this date will be removed; the value should be able to be parsed by DateTime.', 'now');
        $this->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Number of tokens to process per batch', $this->defaultBatchSize);
        $this->setHelp(
            <<<'EOT'
            The <info>%command.name%</info> command revokes all invalid (expired) refresh tokens.
            You can specify a date to revoke tokens that are invalid before that date.
            If no date is specified, it defaults to the current date and time.
            EOT
        );
        $this->setAliases(['gesdinet:jwt:clear-invalid-tokens']);
        $this->setName('gesdinet:jwt:clear-invalid-tokens');
        $this->setDescription('Clear invalid refresh tokens.');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $datetimeArgument = $input->getArgument('datetime');
        $batchSizeOption = $input->getOption('batch-size');

        // Both come off the command line, where an argument may also arrive as an array
        if (!is_string($datetimeArgument) || !is_numeric($batchSizeOption)) {
            $io->error('The datetime and the batch size must each be a single value.');

            return Command::INVALID;
        }

        $datetime = new DateTime($datetimeArgument);
        $batchSize = (int) $batchSizeOption;

        // A batch of zero or less reads nothing, so the command would report that there was
        // nothing to revoke while leaving every expired token in place
        if ($batchSize < 1) {
            $io->error('The batch size must be a positive integer.');

            return Command::INVALID;
        }

        $revokedTokens = $this->refreshTokenManager->revokeAllInvalidBatch($datetime, $batchSize);

        if (0 === count($revokedTokens)) {
            $io->info('There were no invalid tokens to revoke.');
        } else {
            $io->text(sprintf('Revoked %d invalid token(s) in batches of %d', count($revokedTokens), $batchSize));

            // A run clearing a backlog revokes thousands of tokens, and listing them all buries the
            // line that says how many
            if ($output->isVerbose()) {
                $io->listing(array_map(
                    static fn (RefreshTokenInterface $revokedToken): string => $revokedToken->getRefreshToken() ?? '',
                    $revokedTokens,
                ));
            }
        }

        return 0;
    }
}
