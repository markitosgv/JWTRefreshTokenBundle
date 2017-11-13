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
 * All changes to UserRefreshTokens should happen through this interface.
 */
interface RefreshTokenManagerInterface
{
    public function create(): RefreshTokenInterface;

    public function get(string $refreshToken): ? RefreshTokenInterface;

    public function getLastFromUsername(string $username): ? RefreshTokenInterface;

    public function save(RefreshTokenInterface $refreshToken): void;

    public function delete(RefreshTokenInterface $refreshToken);

    /**
     * @return RefreshTokenInterface[]
     */
    public function revokeAllInvalid(): array;

    public function getClass(): string;
}
