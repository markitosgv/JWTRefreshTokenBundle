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

    public function setRefreshToken(string $refreshToken = null): self;

    public function getRefreshToken(): ? string;

    public function setValid(\DateTime $valid): self;

    public function getValid(): ? \DateTime;

    public function setUsername(string $username): self;

    public function getUsername(): ? string;

    public function isValid(): bool;
}
