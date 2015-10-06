<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Command;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RevokeRefreshTokenCommandSpec extends ObjectBehavior
{
    public function it_is_initializable()
    {
        $this->shouldHaveType('Gesdinet\JWTRefreshTokenBundle\Command\RevokeRefreshTokenCommand');
    }

    public function it_is_a_command()
    {
        $this->shouldHaveType('Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand');
    }

    public function it_has_a_name()
    {
        $this->getName()->shouldReturn('gesdinet:jwt:revoke');
    }

    public function it_revokes_a_refresh_token(ContainerInterface $container, InputInterface $input, OutputInterface $output, RefreshTokenManagerInterface $refreshTokenManager, RefreshTokenInterface $refreshToken)
    {
        $container->get('gesdinet.jwtrefreshtoken.refresh_token_manager')->shouldBeCalled()->willReturn($refreshTokenManager);
        $refreshTokenManager->get(Argument::any())->shouldBeCalled()->willReturn($refreshToken);

        $refreshTokenManager->delete($refreshToken)->shouldBeCalled();
        $output->writeln(Argument::any())->shouldBeCalled();

        $this->setContainer($container);
        $this->run($input, $output);
    }

    public function it_not_revokes_a_refresh_token(ContainerInterface $container, InputInterface $input, OutputInterface $output, RefreshTokenManagerInterface $refreshTokenManager, RefreshTokenInterface $refreshToken)
    {
        $container->get('gesdinet.jwtrefreshtoken.refresh_token_manager')->shouldBeCalled()->willReturn($refreshTokenManager);
        $refreshTokenManager->get(Argument::any())->shouldBeCalled()->willReturn(null);

        $this->setContainer($container);
        $this->run($input, $output)->shouldBe(-1);
    }
}
