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
use Gesdinet\JWTRefreshTokenBundle\Model\UserRefreshTokenManager as BaseRefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Model\UserRefreshTokenInterface;

class UserRefreshTokenManager extends BaseRefreshTokenManager
{

    protected $objectManager;
    protected $class;
    protected $repository;

    /**
     * Constructor.
     *
     * @param ObjectManager           $om
     * @param string                  $class
     */
    public function __construct(ObjectManager $om, $class)
    {
        $this->objectManager = $om;
        $this->repository = $om->getRepository($class);
        $metadata = $om->getClassMetadata($class);
        $this->class = $metadata->getName();
    }

    /**
     * @param string                    $refreshToken
     * @param UserRefreshTokenInterface $user
     *
     * @return UserRefreshTokenInterface
     */
    public function get($refreshToken, $user)
    {
        return $this->repository->findOneBy(array('refreshToken' => $refreshToken, 'user' => $user));
    }

    /**
     * @param UserRefreshTokenInterface $user
     *
     * @return UserRefreshTokenInterface
     */
    public function getLastFromUser($user)
    {
        return $this->repository->findOneBy(array('user' => $user), array('valid' => 'DESC'));
    }

    /**
     * @param UserRefreshTokenInterface $userRefreshToken
     * @param boolean                   $andFlush
     *
     * @return void
     */
    public function save(UserRefreshTokenInterface $userRefreshToken, $andFlush = true)
    {
        $this->objectManager->persist($userRefreshToken);

        if ($andFlush) {
            $this->objectManager->flush();
        }
    }

    /**
     * @param UserRefreshTokenInterface $userRefreshToken
     * @param boolean                   $andFlush
     *
     * @return void
     */
    public function delete(UserRefreshTokenInterface $userRefreshToken, $andFlush = true)
    {
        $this->objectManager->remove($userRefreshToken);

        if ($andFlush) {
            $this->objectManager->flush();
        }
    }

    /**
     * Returns the UserRefreshToken fully qualified class name.
     *
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }
}