<?php

declare(strict_types=1);

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Security\Exception;

use Gesdinet\JWTRefreshTokenBundle\Security\Exception\InvalidTokenException;
use Gesdinet\JWTRefreshTokenBundle\Security\Exception\MissingTokenException;
use Gesdinet\JWTRefreshTokenBundle\Security\Exception\TokenNotFoundException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * The message key is what reaches the client, so each failure has to stay distinguishable.
 */
final class ExceptionMessageKeyTest extends TestCase
{
    /**
     * @return iterable<string, array{AuthenticationException, string}>
     */
    public static function exceptionProvider(): iterable
    {
        yield 'missing' => [new MissingTokenException(), 'Missing JWT Refresh Token'];
        yield 'not found' => [new TokenNotFoundException(), 'JWT Refresh Token Not Found'];
        yield 'invalid' => [new InvalidTokenException(), 'Invalid JWT Refresh Token'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('exceptionProvider')]
    public function test_exposes_its_own_message_key(AuthenticationException $exception, string $expected): void
    {
        $this->assertSame($expected, $exception->getMessageKey());
    }
}
