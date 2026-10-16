<?php

declare(strict_types=1);

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Contract;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Tests\Services\UserCreator;

/**
 * What single-use rotation relies on: a delete reports what the storage deleted, not what was
 * there when it looked.
 *
 * Two requests spending the same token have both read it, and on PostgreSQL or MySQL the second
 * delete waits on the row lock and then removes nothing. That is the only signal telling the second
 * request it lost. A test case makes its storage leave the row and delete nothing, which is what the
 * losing delete sees.
 */
trait StorageDecidesTheDeleteContract
{
    abstract protected function makeTheStorageDeleteNothing(): void;

    public function test_reports_nothing_deleted_when_the_storage_deleted_nothing(): void
    {
        $manager = $this->manager();

        $class = $manager->getClass();
        $token = $class::createForUserWithTtl('a-token-spent-concurrently', UserCreator::create('someone'), 600);
        $manager->save($token);

        $this->makeTheStorageDeleteNothing();

        $this->assertSame(0, $manager->delete($token));
    }

    abstract protected function manager(int $batchSize = RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE): RefreshTokenManagerInterface;
}
