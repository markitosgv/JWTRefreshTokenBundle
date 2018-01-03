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

use Doctrine\Common\Persistence\ObjectManager;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManager as BaseRefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;

class RefreshTokenManager extends BaseRefreshTokenManager
{
    protected $objectManager;
    protected $class;
    protected $repository;
    protected $maxTokenByUser;

    /**
     * Constructor.
     *
     * @param ObjectManager $om
     * @param string        $class
     * @param string        $maxTokenByUser
     */
    public function __construct(ObjectManager $om, $class, $maxTokenByUser)
    {
        $this->objectManager = $om;
        $this->repository = $om->getRepository($class);
        $metadata = $om->getClassMetadata($class);
        $this->class = $metadata->getName();
        $this->maxTokenByUser = $maxTokenByUser;
    }

    /**
     * @param string $refreshToken
     *
     * @return RefreshTokenInterface
     */
    public function get($refreshToken)
    {
        return $this->repository->findOneBy(array('refreshToken' => $refreshToken));
    }

    /**
     * @param string $username
     *
     * @return RefreshTokenInterface
     */
    public function getLastFromUsername($username)
    {
        return $this->repository->findOneBy(array('username' => $username), array('valid' => 'DESC'));
    }

    /**
     * @param RefreshTokenInterface $refreshToken
     * @param bool|true             $andFlush
     */
    public function save(RefreshTokenInterface $refreshToken, $andFlush = true)
    {
        $offset = $this->maxTokenByUser;
        if (!$andFlush)
            $offset--;
        $username = $refreshToken->getUsername();
        $this->objectManager->persist($refreshToken);

        if ($andFlush) {
            $this->objectManager->flush();
        }

        $tokens = $this->repository->findBy(['username' => $username], ['valid' => 'DESC'], 1000, $offset);

        $this->revokeTokens($tokens, $andFlush);
    }

    /**
     * @param RefreshTokenInterface $refreshToken
     * @param bool                  $andFlush
     */
    public function delete(RefreshTokenInterface $refreshToken, $andFlush = true)
    {
        $this->objectManager->remove($refreshToken);

        if ($andFlush) {
            $this->objectManager->flush();
        }
    }

    /**
     * @param \DateTime $datetime
     * @param bool      $andFlush
     *
     * @return RefreshTokenInterface[]
     */
    public function revokeAllInvalid($datetime = null, $andFlush = true)
    {
        $invalidTokens = $this->repository->findInvalid($datetime);

        return $this->revokeTokens($invalidTokens, $andFlush);
    }

    /**
    * @param RefreshTokenInterface[] $tokens
    * @param bool                    $andFlush
    *
    * @return RefreshTokenInterface[]
    */
    public function revokeTokens($tokens, $andFlush)
    {
        foreach ($tokens as $token) {
            $this->objectManager->remove($token);
        }

        if ($andFlush) {
            $this->objectManager->flush();
        }

        return $tokens;
    }

    /**
     * @param string $username
     * @param bool   $andFlush
     *
     * @return RefreshTokenInterface[]
     */
    public function revokeAllTokenByUsername($username, $andFlush = true)
    {
        $tokens = $this->repository->findBy(['username' => $username]);

        return $this->revokeTokens($tokens, $andFlush);
    }

    /**
     * Returns the RefreshToken fully qualified class name.
     *
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }
}
