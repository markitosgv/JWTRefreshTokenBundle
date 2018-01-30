<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Command;

use Gesdinet\JWTRefreshTokenBundle\Command\RevokeRefreshTokenCommand;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RevokeRefreshTokenCommandSpec extends ObjectBehavior
{
    public function let(RefreshTokenManagerInterface $refreshTokenManager)
    {
        $this->beConstructedWith($refreshTokenManager);
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType(RevokeRefreshTokenCommand::class);
    }

    public function it_is_a_command()
    {
        $this->shouldHaveType(Command::class);
    }

    public function it_has_a_name()
    {
        $this->getName()->shouldReturn('gesdinet:jwt:revoke');
    }

    public function it_revokes_a_refresh_token(InputInterface $input, OutputInterface $output, RefreshTokenManagerInterface $refreshTokenManager, RefreshTokenInterface $refreshToken)
    {
        $input->bind(Argument::type(InputDefinition::class))->shouldBeCalled();
        $input->isInteractive()->willReturn(false);
        $input->hasArgument(Argument::exact('command'))->willReturn(false);
        $input->validate()->shouldBeCalled();

        $argument = Argument::type('string');
        $input->getArgument(Argument::exact('refresh_token'))->willReturn($argument);
        $refreshTokenManager->get($argument)->shouldBeCalled()->willReturn($refreshToken);

        $refreshTokenManager->delete($refreshToken)->shouldBeCalled();
        $output->writeln(Argument::any())->shouldBeCalled();

        $this->run($input, $output);
    }

    public function it_not_revokes_a_refresh_token(InputInterface $input, OutputInterface $output, RefreshTokenManagerInterface $refreshTokenManager)
    {
        $input->bind(Argument::type(InputDefinition::class))->shouldBeCalled();
        $input->isInteractive()->willReturn(false);
        $input->hasArgument(Argument::exact('command'))->willReturn(false);
        $input->validate()->shouldBeCalled();

        $argument = Argument::type('string');
        $input->getArgument(Argument::exact('refresh_token'))->willReturn($argument);
        $refreshTokenManager->get($argument)->willReturn(null);

        $this->run($input, $output)->shouldBe(-1);
    }
}
