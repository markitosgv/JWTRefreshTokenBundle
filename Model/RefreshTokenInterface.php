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

use Stringable;
use DateTimeInterface;
use Symfony\Component\Security\Core\User\UserInterface;

interface RefreshTokenInterface extends Stringable
{
    /**
     * Creates a new model instance based on the provided details.
     */
    public static function createForUserWithTtl(string $refreshToken, UserInterface $user, int $ttl): RefreshTokenInterface;

    /**
     * @return int|string|null
     */
    public function getId();

    /**
     * @param string $refreshToken
     *
     * @return $this
     */
    public function setRefreshToken($refreshToken);

    /**
     * @return string|null
     */
    public function getRefreshToken();

    /**
     * @param DateTimeInterface|null $valid
     *
     * @return $this
     */
    public function setValid($valid);

    /**
     * @return DateTimeInterface|null
     */
    public function getValid();

    /**
     * @param string|null $username
     *
     * @return $this
     */
    public function setUsername($username);

    /**
     * @return string|null
     */
    public function getUsername();

    /**
     * @return bool
     */
    public function isValid();
}
