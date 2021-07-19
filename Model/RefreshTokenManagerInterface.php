<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Model;

/**
 * Interface to be implemented by user managers. This adds an additional level
 * of abstraction between your application, and the actual repository.
 *
 * All changes to RefreshTokenInterface objects should happen through this interface.
 */
interface RefreshTokenManagerInterface
{
    /**
     * Creates an empty RefreshTokenInterface instance.
     *
     * @return RefreshTokenInterface
     *
     * @deprecated to be removed in 2.0, use a `Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface` instead.
     */
    public function create();

    /**
     * @param string $refreshToken
     *
     * @return RefreshTokenInterface|null
     */
    public function get($refreshToken);

    /**
     * @param string $username
     *
     * @return RefreshTokenInterface|null
     */
    public function getLastFromUsername($username);

    /**
     * @return void
     */
    public function save(RefreshTokenInterface $refreshToken);

    /**
     * @return void
     */
    public function delete(RefreshTokenInterface $refreshToken);

    /**
     * @param \DateTimeInterface|null $datetime
     *
     * @return RefreshTokenInterface[]
     */
    public function revokeAllInvalid($datetime = null);

    /**
     * Returns the fully qualified class name for a concrete RefreshTokenInterface class.
     *
     * @return class-string<RefreshTokenInterface>
     */
    public function getClass();
}
