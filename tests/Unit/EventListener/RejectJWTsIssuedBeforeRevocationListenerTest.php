<?php

declare(strict_types=1);

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\EventListener;

use Gesdinet\JWTRefreshTokenBundle\EventListener\RejectJWTsIssuedBeforeRevocationListener;
use Gesdinet\JWTRefreshTokenBundle\Security\Revocation\JWTRevocationRegistryInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use PHPUnit\Framework\TestCase;

final class RejectJWTsIssuedBeforeRevocationListenerTest extends TestCase
{
    /**
     * The ordinary case, and the one that runs on every authenticated request.
     */
    public function test_leaves_a_jwt_alone_when_the_user_was_never_revoked(): void
    {
        $event = new JWTDecodedEvent(['username' => 'someone', 'iat' => time()]);

        $this->listenerWith(null)($event);

        $this->assertTrue($event->isValid());
    }

    /**
     * The point of the whole thing: revoking a user's refresh tokens used to leave every JWT already
     * issued working until it expired, which is the wrong outcome at the moment it matters most.
     */
    public function test_refuses_a_jwt_issued_before_the_revocation(): void
    {
        $event = new JWTDecodedEvent(['username' => 'someone', 'iat' => time() - 600]);

        $this->listenerWith(time() - 300)($event);

        $this->assertFalse($event->isValid());
    }

    /**
     * A JWT issued after the revocation is one the user got by signing in again, which is exactly
     * what they were meant to do.
     */
    public function test_accepts_a_jwt_issued_after_the_revocation(): void
    {
        $event = new JWTDecodedEvent(['username' => 'someone', 'iat' => time()]);

        $this->listenerWith(time() - 300)($event);

        $this->assertTrue($event->isValid());
    }

    /**
     * `iat` has second resolution, so a JWT stamped with the same second as the revocation may have
     * been issued just before it. Refusing costs its holder one trip through a login they are being
     * sent to anyway; accepting leaves a revoked session alive.
     */
    public function test_refuses_a_jwt_issued_in_the_same_second_as_the_revocation(): void
    {
        $at = time() - 300;
        $event = new JWTDecodedEvent(['username' => 'someone', 'iat' => $at]);

        $this->listenerWith($at)($event);

        $this->assertFalse($event->isValid());
    }

    public function test_asks_about_the_user_the_payload_names(): void
    {
        $registry = $this->createMock(JWTRevocationRegistryInterface::class);
        $registry
            ->expects($this->once())
            ->method('revokedBefore')
            ->with('someone')
            ->willReturn(null);

        (new RejectJWTsIssuedBeforeRevocationListener($registry))(
            new JWTDecodedEvent(['username' => 'someone', 'iat' => time()])
        );
    }

    /**
     * Lexik's user_id_claim is configurable, and a payload keyed by something else would otherwise
     * be judged against nobody.
     */
    public function test_reads_the_user_from_the_configured_claim(): void
    {
        $registry = $this->createMock(JWTRevocationRegistryInterface::class);
        $registry
            ->expects($this->once())
            ->method('revokedBefore')
            ->with('someone@example.com')
            ->willReturn(null);

        (new RejectJWTsIssuedBeforeRevocationListener($registry, 'email'))(
            new JWTDecodedEvent(['email' => 'someone@example.com', 'iat' => time()])
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function payloadsThisCannotJudge(): iterable
    {
        yield 'no user claim' => [['iat' => 100]];
        yield 'no issued-at claim' => [['username' => 'someone']];
        yield 'a user claim that is not a string' => [['username' => ['someone'], 'iat' => 100]];
        yield 'an issued-at claim that is not a number' => [['username' => 'someone', 'iat' => 'yesterday']];
    }

    /**
     * Refusing these would break every application whose tokens simply carry different claims, so
     * they are left to the rest of the verification — which is what was deciding before this
     * listener existed.
     *
     * @param array<string, mixed> $payload
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('payloadsThisCannotJudge')]
    public function test_leaves_a_payload_it_cannot_judge_to_the_rest_of_the_verification(array $payload): void
    {
        $registry = $this->createMock(JWTRevocationRegistryInterface::class);
        $registry->expects($this->never())->method('revokedBefore');

        $event = new JWTDecodedEvent($payload);

        (new RejectJWTsIssuedBeforeRevocationListener($registry))($event);

        $this->assertTrue($event->isValid());
    }

    private function listenerWith(?int $revokedBefore): RejectJWTsIssuedBeforeRevocationListener
    {
        $registry = $this->createStub(JWTRevocationRegistryInterface::class);
        $registry->method('revokedBefore')->willReturn($revokedBefore);

        return new RejectJWTsIssuedBeforeRevocationListener($registry);
    }
}
