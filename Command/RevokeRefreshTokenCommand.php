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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ClearInvalidRefreshTokensCommand.
 */
class RevokeRefreshTokenCommand extends Command
{
    /**
     * @var RefreshTokenManagerInterface
     */
    private $refreshTokenManager;

    public function __construct(RefreshTokenManagerInterface $refreshTokenManager)
    {
        parent::__construct();
        $this->refreshTokenManager = $refreshTokenManager;
    }

    protected function configure(): void
    {
        $this
            ->setName('gesdinet:jwt:revoke')
            ->setDescription('Revoke a refresh token')
            ->setDefinition(
                [
                    new InputArgument('refresh_token', InputArgument::REQUIRED, 'The refresh token to revoke'),
                ]
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $refreshTokenParam = $input->getArgument('refresh_token');

        $refreshToken = $this->refreshTokenManager->get($refreshTokenParam);

        if ($refreshToken instanceof RefreshTokenInterface) {
            $this->refreshTokenManager->delete($refreshToken);

            $output->writeln(sprintf('Revoke <comment>%s</comment>', $refreshToken->getRefreshToken()));
        } else {
            $output->writeln(
                sprintf(
                    '<error>Not Found:</error> Refresh Token <comment>%s</comment> doesn\'t exists',
                    $refreshTokenParam
                )
            );
        }
    }
}
