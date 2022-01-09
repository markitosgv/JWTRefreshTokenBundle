<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;

abstract class AbstractRefreshTokenTest extends TestCase
{
    private RefreshTokenInterface $refreshToken;

    protected function setUp(): void
    {
        $username = 'username';
        $password = 'password';

        if (class_exists(InMemoryUser::class)) {
            $user = new InMemoryUser($username, $password);
        } else {
            $user = new User($username, $password);
        }

        $this->refreshToken = $this->createRefreshToken('token', $user, 600);
    }

    abstract protected function createRefreshToken(
        string $refreshToken,
        UserInterface $user,
        int $ttl
    ): RefreshTokenInterface;

    public function testCanBeConvertedToAString()
    {
        $this->assertSame('token', $this->refreshToken->__toString());
    }

    public function testHasNoIdByDefault()
    {
        $this->assertNull($this->refreshToken->getId());
    }

    public function testHasACustomRefreshToken()
    {
        $this->assertSame($this->refreshToken, $this->refreshToken->setRefreshToken('custom-token'));
        $this->assertSame('custom-token', $this->refreshToken->getRefreshToken());
    }

    public function testGeneratesARefreshToken()
    {
        $this->assertSame($this->refreshToken, $this->refreshToken->setRefreshToken());
        $this->assertIsString($this->refreshToken->getRefreshToken());
    }

    public function testHasUsername()
    {
        $this->assertSame('username', $this->refreshToken->getUsername());
    }

    public function testHasAValidTimestamp()
    {
        $this->assertInstanceOf(\DateTimeInterface::class, $this->refreshToken->getValid());
    }

    public function testValid()
    {
        $date = new \DateTime();
        $date->modify('+1 day');
        $this->refreshToken->setValid($date);
        $this->assertTrue($this->refreshToken->isValid());
    }

    public function testNotValid()
    {
        $date = new \DateTime();
        $date->modify('-1 day');
        $this->refreshToken->setValid($date);
        $this->assertFalse($this->refreshToken->isValid());
    }
}
