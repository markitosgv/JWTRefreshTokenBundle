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

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ClearInvalidRefreshTokensCommand.
 */
class RevokeRefreshTokenCommand extends ContainerAwareCommand
{
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
                new InputArgument('username', InputArgument::REQUIRED, 'The user of this refresh token'),
            ));
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $refreshToken = $input->getArgument('refresh_token');
        $username = $input->getArgument('username');

        $user = $this->get('gesdinet.jwtrefreshtoken.user_manager')->findUserByUsername($username);

        if (null === $user) {
            $output->writeln(sprintf('<error>Not Found:</error> User <comment>%s</comment> doesn\'t exists', $username));

            return -1;
        }

        $manager = $this->getContainer()->get('gesdinet.jwtrefreshtoken.refresh_token_manager');
        $userRefreshToken = $manager->get($refreshToken, $user);

        if (null === $userRefreshToken) {
            $output->writeln(sprintf('<error>Not Found:</error> Refresh Token <comment>%s</comment> for user <comment>%s</comment> doesn\'t exists', $refreshToken, $username));

            return -1;
        }

        $manager->delete($userRefreshToken);

        $output->writeln(sprintf('Revoke <comment>%s</comment> for user %s', $userRefreshToken->getRefreshToken(), $userRefreshToken->getUserName()));
    }
}
