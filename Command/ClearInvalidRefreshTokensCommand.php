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
class ClearInvalidRefreshTokensCommand extends Command
{
    /**
     * @deprecated
     */
    protected static $defaultName = 'gesdinet:jwt:clear';

    private RefreshTokenManagerInterface $refreshTokenManager;

    public function __construct(RefreshTokenManagerInterface $refreshTokenManager)
    {
        parent::__construct();

        $this->refreshTokenManager = $refreshTokenManager;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Clear invalid refresh tokens.')
            ->addArgument('datetime', InputArgument::OPTIONAL, 'An optional date, all tokens before this date will be removed; the value should be able to be parsed by DateTime.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string|null $datetime */
        $datetime = $input->getArgument('datetime');

        if (null === $datetime) {
            $datetime = new \DateTime();
        } else {
            $datetime = new \DateTime($datetime);
        }

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
