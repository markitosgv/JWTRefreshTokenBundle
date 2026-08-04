<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\EventListener;

use Gesdinet\JWTRefreshTokenBundle\Event\RefreshEvent;
use Gesdinet\JWTRefreshTokenBundle\EventListener\BlockPreviousJWTListener;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\MissingClaimException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\BlockedTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

#[AllowMockObjectsWithoutExpectations]
final class BlockPreviousJWTListenerTest extends TestCase
{
    private BlockedTokenManagerInterface&MockObject $blockedTokens;
    private TokenExtractorInterface&MockObject $extractor;
    private JWTTokenManagerInterface&MockObject $jwtManager;
    protected function setUp(): void
    {
        $this->blockedTokens = $this->createMock(BlockedTokenManagerInterface::class);
        $this->extractor = $this->createMock(TokenExtractorInterface::class);
        $this->jwtManager = $this->createMock(JWTTokenManagerInterface::class);
    }

    public function test_blocks_the_jwt_the_refresh_replaced(): void
    {
        $payload = ['username' => 'someone', 'exp' => 1785283200];

        $this->extractor->method('extract')->willReturn('the.previous.jwt');
        $this->jwtManager->expects($this->once())->method('parse')->with('the.previous.jwt')->willReturn($payload);

        $this->blockedTokens->expects($this->once())->method('add')->with($payload);

        ($this->listener())($this->event());
    }

    /**
     * Refreshing does not require a JWT, and a client that throws its own away before refreshing is
     * the ordinary shape. There is nothing to block.
     */
    public function test_does_nothing_when_the_request_carried_no_jwt(): void
    {
        $this->extractor->method('extract')->willReturn(false);

        $this->blockedTokens->expects($this->never())->method('add');

        ($this->listener())($this->event());
    }

    /**
     * An expired JWT is refused everywhere already, so recording it would fill the store to no end.
     * This is the case that looks like it should be blocked and is the one worth leaving alone.
     */
    public function test_leaves_a_jwt_that_no_longer_parses_alone(): void
    {
        $this->extractor->method('extract')->willReturn('the.expired.jwt');
        $this->jwtManager->method('parse')->willThrowException(
            new JWTDecodeFailureException(JWTDecodeFailureException::EXPIRED_TOKEN, 'Expired JWT Token')
        );

        $this->blockedTokens->expects($this->never())->method('add');

        ($this->listener())($this->event());
    }

    public function test_survives_a_payload_the_blocklist_cannot_key_on(): void
    {
        $this->extractor->method('extract')->willReturn('the.previous.jwt');
        $this->jwtManager->method('parse')->willReturn(['nothing' => 'useful']);
        $this->blockedTokens->method('add')->willThrowException(new MissingClaimException('jti'));

        ($this->listener())($this->event());

        $this->addToAssertionCount(1);
    }

    private function listener(): BlockPreviousJWTListener
    {
        return new BlockPreviousJWTListener(
            $this->blockedTokens,
            $this->extractor,
            $this->jwtManager
        );
    }

    private function event(): RefreshEvent
    {
        return new RefreshEvent(
            $this->createStub(RefreshTokenInterface::class),
            $this->createStub(TokenInterface::class),
            'api',
            Request::create('/api/token/refresh', 'POST')
        );
    }
}
