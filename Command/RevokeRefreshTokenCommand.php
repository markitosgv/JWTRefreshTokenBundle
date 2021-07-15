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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RevokeRefreshTokenCommand extends Command
{
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
        $refreshTokenParam = $input->getArgument('refresh_token');

        $refreshToken = $this->refreshTokenManager->get($refreshTokenParam);

        if (null === $refreshToken) {
            $output->writeln(sprintf('<error>Not Found:</error> Refresh Token <comment>%s</comment> doesn\'t exist', $refreshTokenParam));

            return 1;
        }

        $this->refreshTokenManager->delete($refreshToken);

        $output->writeln(sprintf('Revoked <comment>%s</comment>', $refreshToken->getRefreshToken()));

        return 0;
    }
}
