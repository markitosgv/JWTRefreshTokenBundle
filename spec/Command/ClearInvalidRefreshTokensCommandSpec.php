<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Command;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClearInvalidRefreshTokensCommandSpec extends ObjectBehavior
{
    public function let(RefreshTokenManagerInterface $refreshTokenManager)
    {
        $this->beConstructedWith($refreshTokenManager);
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType('Gesdinet\JWTRefreshTokenBundle\Command\ClearInvalidRefreshTokensCommand');
    }

    public function it_is_a_command()
    {
        $this->shouldHaveType('Symfony\Component\Console\Command\Command');
    }

    public function it_has_a_name()
    {
        $this->getName()->shouldReturn('gesdinet:jwt:clear');
    }

    public function it_clears_invalid_refresh_tokens(InputInterface $input, OutputInterface $output, RefreshTokenManagerInterface $refreshTokenManager, RefreshTokenInterface $revokedToken)
    {
        $refreshTokenManager->revokeAllInvalid(Argument::any())->shouldBeCalled()->willReturn([$revokedToken]);

        $output->writeln(Argument::any())->shouldBeCalled();

        $this->run($input, $output);
    }
}
