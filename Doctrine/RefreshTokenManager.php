<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Doctrine;

use Doctrine\Persistence\ObjectManager;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;

class RefreshTokenManager implements RefreshTokenManagerInterface
{
    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @var class-string<RefreshTokenInterface>
     */
    protected $class;

    /**
     * @var RefreshTokenRepositoryInterface<RefreshTokenInterface>
     */
    protected $repository;

    /**
     * @param class-string<RefreshTokenInterface> $class
     *
     * @throws \LogicException if the object repository does not implement `Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenRepositoryInterface`
     */
    public function __construct(ObjectManager $om, $class)
    {
        $this->objectManager = $om;

        $repository = $om->getRepository($class);

        if (!$repository instanceof RefreshTokenRepositoryInterface) {
            throw new \LogicException(sprintf('Repository mapped for "%s" should implement %s.', $class, RefreshTokenRepositoryInterface::class));
        }

        $this->repository = $repository;

        $metadata = $om->getClassMetadata($class);
        $this->class = $metadata->getName();
    }

    /**
     * Creates an empty RefreshTokenInterface instance.
     *
     * @return RefreshTokenInterface
     *
     * @deprecated to be removed in 2.0, use a `Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface` instead.
     */
    public function create()
    {
        trigger_deprecation('gesdinet/jwt-refresh-token-bundle', '1.0', '%s() is deprecated and will be removed in 2.0, use a "%s" instance to create new %s objects.', __METHOD__, RefreshTokenGeneratorInterface::class, RefreshTokenInterface::class);

        $class = $this->getClass();

        return new $class();
    }

    /**
     * @param string $refreshToken
     *
     * @return RefreshTokenInterface|null
     */
    public function get($refreshToken)
    {
        return $this->repository->findOneBy(['refreshToken' => $refreshToken]);
    }

    /**
     * @param string $username
     *
     * @return RefreshTokenInterface|null
     */
    public function getLastFromUsername($username)
    {
        return $this->repository->findOneBy(['username' => $username], ['valid' => 'DESC']);
    }

    /**
     * @param bool $andFlush
     *
     * @return void
     */
    public function save(RefreshTokenInterface $refreshToken, $andFlush = true)
    {
        $this->objectManager->persist($refreshToken);

        if ($andFlush) {
            $this->objectManager->flush();
        }
    }

    /**
     * @param bool $andFlush
     *
     * @return void
     */
    public function delete(RefreshTokenInterface $refreshToken, $andFlush = true)
    {
        $this->objectManager->remove($refreshToken);

        if ($andFlush) {
            $this->objectManager->flush();
        }
    }

    /**
     * @param \DateTimeInterface|null $datetime
     * @param bool                    $andFlush
     *
     * @return RefreshTokenInterface[]
     */
    public function revokeAllInvalid($datetime = null, $andFlush = true)
    {
        $invalidTokens = $this->repository->findInvalid($datetime);

        foreach ($invalidTokens as $invalidToken) {
            $this->objectManager->remove($invalidToken);
        }

        if ($andFlush) {
            $this->objectManager->flush();
        }

        return $invalidTokens;
    }

    /**
     * Returns the RefreshToken fully qualified class name.
     *
     * @return class-string<RefreshTokenInterface>
     */
    public function getClass()
    {
        return $this->class;
    }
}
