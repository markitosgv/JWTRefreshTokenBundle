<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Contract;

use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Document\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\ODMTestCase;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

#[RequiresPhpExtension('mongodb')]
final class OdmRefreshTokenManagerTest extends ODMTestCase
{
    use RefreshTokenManagerContract;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ($this->documentManager->getMetadataFactory()->getAllMetadata() as $class) {
            if ($class->isMappedSuperclass || $class->isEmbeddedDocument || $class->isQueryResultDocument) {
                continue;
            }

            $this->documentManager->getSchemaManager()->createDocumentCollection($class->name);
        }

        $this->documentManager->getSchemaManager()->ensureIndexes();
    }

    /**
     * @param positive-int $batchSize
     */
    protected function manager(int $batchSize = RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE): RefreshTokenManagerInterface
    {
        return new RefreshTokenManager($this->documentManager, RefreshToken::class, $batchSize);
    }
}
