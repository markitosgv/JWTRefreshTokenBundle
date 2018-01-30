<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Command;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Gesdinet\JWTRefreshTokenBundle\Command\ClearInvalidRefreshTokensCommand;
use Symfony\Component\Console\Command\Command;

class ClearInvalidRefreshTokensCommandSpec extends ObjectBehavior
{
    public function let(RefreshTokenManagerInterface $refreshTokenManager)
    {
        $this->beConstructedWith($refreshTokenManager);
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType(ClearInvalidRefreshTokensCommand::class);
    }

    public function it_is_a_command()
    {
        $this->shouldHaveType(Command::class);
    }

    public function it_has_a_name()
    {
        $this->getName()->shouldReturn('gesdinet:jwt:clear');
    }

    public function it_clears_invalid_refresh_tokens(InputInterface $input, OutputInterface $output, RefreshTokenManagerInterface $refreshTokenManager, RefreshTokenInterface $revokedToken)
    {
        $refreshTokenManager->revokeAllInvalid(Argument::any())->shouldBeCalled()->willReturn(array($revokedToken));

        $output->writeln(Argument::any())->shouldBeCalled();

        $this->run($input, $output);
    }
}
