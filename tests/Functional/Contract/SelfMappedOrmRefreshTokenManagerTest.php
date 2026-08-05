<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Contract;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\SchemaTool;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Entity\SelfMappedRefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Entity\User;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\ORMTestCase;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

/**
 * The contract held against an entity that maps itself rather than inheriting the bundle's mapping.
 *
 * An application that has to choose its own identifier strategy, which is what Doctrine asks for on
 * PostgreSQL, cannot do it by extending the shipped mapped superclass: the identifier is declared
 * there and Doctrine refuses to have it declared twice. Extending the model instead is the way
 * round it, and this is what keeps that a supported path rather than advice in an old issue.
 */
#[RequiresPhpExtension('pdo_sqlite')]
final class SelfMappedOrmRefreshTokenManagerTest extends ORMTestCase
{
    use RefreshTokenManagerContract;

    protected function setUp(): void
    {
        parent::setUp();

        new SchemaTool($this->entityManager)->createSchema([
            $this->entityManager->getClassMetadata(SelfMappedRefreshToken::class),
            $this->entityManager->getClassMetadata(User::class),
        ]);
    }

    public function test_the_identifier_strategy_is_the_one_the_application_chose(): void
    {
        $metadata = $this->entityManager->getClassMetadata(SelfMappedRefreshToken::class);

        $this->assertTrue($metadata->isIdentifierNatural() || $metadata->usesIdGenerator());
        $this->assertSame(
            ClassMetadata::GENERATOR_TYPE_IDENTITY,
            $metadata->generatorType,
            'AUTO is what the application is trying to get away from'
        );
    }

    /**
     * @param positive-int $batchSize
     */
    protected function manager(int $batchSize = RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE): RefreshTokenManagerInterface
    {
        return new RefreshTokenManager($this->entityManager, SelfMappedRefreshToken::class, $batchSize);
    }
}
