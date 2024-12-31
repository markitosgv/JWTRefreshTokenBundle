<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Command;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'gesdinet:jwt:clear', description: 'Clear invalid refresh tokens.')]
final class ClearInvalidRefreshTokensCommand extends Command
{
    private RefreshTokenManagerInterface $refreshTokenManager;

    public function __construct(RefreshTokenManagerInterface $refreshTokenManager)
    {
        parent::__construct();

        $this->refreshTokenManager = $refreshTokenManager;
    }

    protected function configure(): void
    {
        $this->addArgument('datetime', InputArgument::OPTIONAL, 'An optional date, all tokens before this date will be removed; the value should be able to be parsed by DateTime.', 'now');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $datetime = new \DateTime($input->getArgument('datetime'));

        $revokedTokens = $this->refreshTokenManager->revokeAllInvalid($datetime);

        if (0 === count($revokedTokens)) {
            $io->info('There were no invalid tokens to revoke.');
        } else {
            $io->text(sprintf('Revoked %d invalid token(s)', count($revokedTokens)));
            $io->listing(array_map(
                static fn (RefreshTokenInterface $revokedToken): string => $revokedToken->getRefreshToken(),
                $revokedTokens,
            ));
        }

        return 0;
    }
}
