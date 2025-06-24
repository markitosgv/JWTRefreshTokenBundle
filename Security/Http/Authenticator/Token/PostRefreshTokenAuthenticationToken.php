<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Security\Http\Authenticator\Token;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

class PostRefreshTokenAuthenticationToken extends PostAuthenticationToken
{
    /**
     * @param string[] $roles An array of roles
     */
    public function __construct(
        UserInterface $user,
        string $firewallName,
        array $roles,
        private RefreshTokenInterface $refreshToken
    ) {
        parent::__construct($user, $firewallName, $roles);
    }

    public function getRefreshToken(): RefreshTokenInterface
    {
        return $this->refreshToken;
    }

    /**
     * {@inheritdoc}
     */
    public function __serialize(): array
    {
        return [$this->refreshToken, parent::__serialize()];
    }

    /**
     * {@inheritdoc}
     */
    public function __unserialize(array $data): void
    {
        [$this->refreshToken, $parentData] = $data;
        parent::__unserialize($parentData);
    }
}
