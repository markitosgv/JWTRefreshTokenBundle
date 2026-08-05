<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Model;

use Gesdinet\JWTRefreshTokenBundle\Document\RefreshToken as DocumentRefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as EntityRefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Model\FamilyAwareRefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Tests\Services\UserCreator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Both token classes that ship with the bundle carry a family, and they carry it the same way.
 */
final class RefreshTokenFamilyTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<FamilyAwareRefreshTokenInterface>}>
     */
    public static function tokenClasses(): iterable
    {
        yield 'entity' => [EntityRefreshToken::class];
        yield 'document' => [DocumentRefreshToken::class];
    }

    /**
     * @param class-string<FamilyAwareRefreshTokenInterface> $class
     */
    #[DataProvider('tokenClasses')]
    public function test_a_freshly_created_token_belongs_to_no_family_yet(string $class): void
    {
        $token = $class::createForUserWithTtl('a-token', UserCreator::create(), 600);

        // The family is put on by whoever issues the token, not by the model: only the issuer knows
        // whether this is a new login or the continuation of an existing chain.
        $this->assertNull($token->getFamily());
    }

    /**
     * @param class-string<FamilyAwareRefreshTokenInterface> $class
     */
    #[DataProvider('tokenClasses')]
    public function test_the_family_is_kept_once_set(string $class): void
    {
        $token = $class::createForUserWithTtl('a-token', UserCreator::create(), 600);

        $this->assertSame($token, $token->setFamily('a-family'), 'The setter is fluent like the rest of the model');
        $this->assertSame('a-family', $token->getFamily());
    }

    /**
     * @param class-string<FamilyAwareRefreshTokenInterface> $class
     */
    #[DataProvider('tokenClasses')]
    public function test_it_is_recognisable_as_family_aware(string $class): void
    {
        // What every feature built on families checks before assuming there is one.
        $this->assertInstanceOf(
            FamilyAwareRefreshTokenInterface::class,
            $class::createForUserWithTtl('a-token', UserCreator::create(), 600)
        );
    }
}
