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

use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ClearInvalidRefreshTokensCommand.
 */
class ClearInvalidRefreshTokensCommand extends Command
{
    /**
     * @var RefreshTokenManager
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
            ->setName('gesdinet:jwt:clear')
            ->setDescription('Clear invalid refresh tokens.')
            ->setDefinition(
                [
                    new InputArgument('datetime', InputArgument::OPTIONAL),
                ]
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $datetime = $input->getArgument('datetime');

        if (null === $datetime) {
            $datetime = new \DateTime();
        } else {
            $datetime = new \DateTime($datetime);
        }

        $revokedTokens = $this->refreshTokenManager->revokeAllInvalid($datetime);

        foreach ($revokedTokens as $revokedToken) {
            $output->writeln(sprintf('Revoke <comment>%s</comment>', $revokedToken->getRefreshToken()));
        }
    }
}
