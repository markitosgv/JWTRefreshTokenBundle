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

/**
 * Class ClearInvalidRefreshTokensCommand.
 */
class RevokeRefreshTokenCommand extends Command
{
    /**
     * @var RefreshTokenManagerInterface
     */
    private $refreshTokenManager;

    /**
     * ClearInvalidRefreshTokensCommand constructor.
     *
     * @param RefreshTokenManagerInterface $refreshTokenManager
     */
    public function __construct(RefreshTokenManagerInterface $refreshTokenManager)
    {
        $this->refreshTokenManager = $refreshTokenManager;
    }

    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('gesdinet:jwt:revoke')
            ->setDescription('Revoke a refresh token')
            ->setDefinition(array(
                new InputArgument('refresh_token', InputArgument::REQUIRED, 'The refresh token to revoke'),
            ));
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $refreshTokenParam = $input->getArgument('refresh_token');

        $refreshToken = $this->refreshTokenManager->get($refreshTokenParam);

        if (null === $refreshToken) {
            $output->writeln(sprintf('<error>Not Found:</error> Refresh Token <comment>%s</comment> doesn\'t exists', $refreshTokenParam));

            return -1;
        }

        $this->refreshTokenManager->delete($refreshToken);

        $output->writeln(sprintf('Revoke <comment>%s</comment>', $refreshToken->getRefreshToken()));
    }
}
