<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Security\Http\Authenticator\Token;

use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Security\Http\Authenticator\Token\PostRefreshTokenAuthenticationToken;
use Gesdinet\JWTRefreshTokenBundle\Tests\Services\UserCreator;
use PHPUnit\Framework\TestCase;

final class PostRefreshTokenAuthenticationTokenTest extends TestCase
{
    public function test_carries_the_refresh_token_that_authenticated_the_user(): void
    {
        $refreshToken = new RefreshToken();

        $token = new PostRefreshTokenAuthenticationToken(UserCreator::create(), 'api', ['ROLE_USER'], $refreshToken);

        $this->assertSame($refreshToken, $token->getRefreshToken());
    }

    /**
     * The token is stored in the session, so it has to survive a serialization round trip with the
     * refresh token and the state of the parent token intact.
     */
    public function test_keeps_its_state_through_a_serialization_round_trip(): void
    {
        $refreshToken = new RefreshToken();
        $refreshToken->setRefreshToken('thepreviouslyissuedrefreshtoken');

        $token = new PostRefreshTokenAuthenticationToken(UserCreator::create('user@localhost'), 'api', ['ROLE_USER'], $refreshToken);

        /** @var PostRefreshTokenAuthenticationToken $restored */
        $restored = unserialize(serialize($token));

        $this->assertSame('thepreviouslyissuedrefreshtoken', $restored->getRefreshToken()->getRefreshToken());
        $this->assertSame('user@localhost', $restored->getUserIdentifier());
        $this->assertSame(['ROLE_USER'], $restored->getRoleNames());
        $this->assertSame('api', $restored->getFirewallName());
    }
}
