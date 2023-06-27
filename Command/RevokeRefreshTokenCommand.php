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

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'gesdinet:jwt:revoke', description: 'Revoke a refresh token')]
class RevokeRefreshTokenCommand extends Command
{
    /**
     * @deprecated
     */
    protected static $defaultName = 'gesdinet:jwt:revoke';

    private RefreshTokenManagerInterface $refreshTokenManager;

    public function __construct(RefreshTokenManagerInterface $refreshTokenManager)
    {
        parent::__construct();

        $this->refreshTokenManager = $refreshTokenManager;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Revoke a refresh token')
            ->addArgument('refresh_token', InputArgument::REQUIRED, 'The refresh token to revoke');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $refreshTokenParam */
        $refreshTokenParam = $input->getArgument('refresh_token');

        $refreshToken = $this->refreshTokenManager->get($refreshTokenParam);

        if (null === $refreshToken) {
            $io->error(sprintf('Refresh token "%s" does not exist', $refreshTokenParam));

            return 1;
        }

        $this->refreshTokenManager->delete($refreshToken);

        $io->success(sprintf('Revoked refresh token "%s"', $refreshTokenParam));

        return 0;
    }
}
