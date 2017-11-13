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

interface RefreshTokenInterface
{

    public function getId(): ? int;

    public function setRefreshToken(string $refreshToken = null): RefreshTokenInterface;

    public function getRefreshToken(): ? string;

    public function setValid(\DateTime $valid): RefreshTokenInterface;

    public function getValid(): ? \DateTime;

    public function setUsername(string $username): RefreshTokenInterface;

    public function getUsername(): ? string;

    public function isValid(): bool;
}
