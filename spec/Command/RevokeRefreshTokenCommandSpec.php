<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Command;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
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
        $this->shouldHaveType('Gesdinet\JWTRefreshTokenBundle\Command\RevokeRefreshTokenCommand');
    }

    public function it_is_a_command()
    {
        $this->shouldHaveType('Symfony\Component\Console\Command\Command');
    }

    public function it_has_a_name()
    {
        $this->getName()->shouldReturn('gesdinet:jwt:revoke');
    }

    public function it_revokes_a_refresh_token(InputInterface $input, OutputInterface $output, RefreshTokenManagerInterface $refreshTokenManager, RefreshTokenInterface $refreshToken)
    {
        $refreshTokenManager->get(Argument::any())->shouldBeCalled()->willReturn($refreshToken);

        $refreshTokenManager->delete($refreshToken)->shouldBeCalled();
        $output->writeln(Argument::any())->shouldBeCalled();

        $this->run($input, $output);
    }

    public function it_not_revokes_a_refresh_token(InputInterface $input, OutputInterface $output, RefreshTokenManagerInterface $refreshTokenManager, RefreshTokenInterface $refreshToken)
    {
        $refreshTokenManager->get(Argument::any())->shouldBeCalled()->willReturn(null);

        $this->run($input, $output)->shouldBe(-1);
    }
}
