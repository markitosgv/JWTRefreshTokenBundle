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
class ClearInvalidRefreshTokensCommand extends Command
{
    protected static $defaultName = 'gesdinet:jwt:clear';

    private $refreshTokenManager;

    public function __construct(RefreshTokenManagerInterface $refreshTokenManager)
    {
        parent::__construct();

        $this->refreshTokenManager = $refreshTokenManager;
    }

    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDescription('Clear invalid refresh tokens.')
            ->addArgument('datetime', InputArgument::OPTIONAL);
    }

    /**
     * @see Command
     */
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

        return 0;
    }
}
