<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Command;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ClearInvalidRefreshTokensCommandSpec extends ObjectBehavior
{
    public function it_is_initializable()
    {
        $this->shouldHaveType('Gesdinet\JWTRefreshTokenBundle\Command\ClearInvalidRefreshTokensCommand');
    }

    public function it_is_a_command()
    {
        $this->shouldHaveType('Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand');
    }

    public function it_has_a_name()
    {
        $this->getName()->shouldReturn('gesdinet:jwt:clear');
    }

    public function it_clears_invalid_refresh_tokens(ContainerInterface $container, InputInterface $input, OutputInterface $output, RefreshTokenManagerInterface $refreshTokenManager, RefreshTokenInterface $revokedToken)
    {
        $container->get('gesdinet.jwtrefreshtoken.refresh_token_manager')->shouldBeCalled()->willReturn($refreshTokenManager);
        $refreshTokenManager->revokeAllInvalid(Argument::any())->shouldBeCalled()->willReturn(array($revokedToken));

        $output->writeln(Argument::any())->shouldBeCalled();

        $this->setContainer($container);
        $this->run($input, $output);
    }
}
