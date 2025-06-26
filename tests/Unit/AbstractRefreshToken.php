<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit;

use DateTimeInterface;
use DateTime;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Tests\Services\UserCreator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

abstract class AbstractRefreshToken extends TestCase
{
    private RefreshTokenInterface $refreshToken;

    protected function setUp(): void
    {
        $this->refreshToken = $this->createRefreshToken('token', UserCreator::create(), 600);
    }

    abstract protected function createRefreshToken(
        string $refreshToken,
        UserInterface $user,
        int $ttl
    ): RefreshTokenInterface;

    public function testCanBeConvertedToAString(): void
    {
        $this->assertSame('token', $this->refreshToken->__toString());
    }

    public function testHasNoIdByDefault(): void
    {
        $this->assertNull($this->refreshToken->getId());
    }

    public function testHasACustomRefreshToken(): void
    {
        $this->assertSame($this->refreshToken, $this->refreshToken->setRefreshToken('custom-token'));
        $this->assertSame('custom-token', $this->refreshToken->getRefreshToken());
    }

    public function testHasUsername(): void
    {
        $this->assertSame('username', $this->refreshToken->getUsername());
    }

    public function testHasAValidTimestamp(): void
    {
        $this->assertInstanceOf(DateTimeInterface::class, $this->refreshToken->getValid());
    }

    public function testValid(): void
    {
        $date = new DateTime();
        $date->modify('+1 day');
        $this->refreshToken->setValid($date);
        $this->assertTrue($this->refreshToken->isValid());
    }

    public function testNotValid(): void
    {
        $date = new DateTime();
        $date->modify('-1 day');
        $this->refreshToken->setValid($date);
        $this->assertFalse($this->refreshToken->isValid());
    }
}
