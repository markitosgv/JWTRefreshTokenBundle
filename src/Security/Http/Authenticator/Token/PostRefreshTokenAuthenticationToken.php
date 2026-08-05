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
use UnexpectedValueException;

final class PostRefreshTokenAuthenticationToken extends PostAuthenticationToken
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
    #[\Override]
    public function __serialize(): array
    {
        return [$this->refreshToken, parent::__serialize()];
    }

    /**
     * @param array<array-key, mixed> $data the refresh token followed by the parent state, as produced by {@see self::__serialize()}
     */
    #[\Override]
    public function __unserialize(array $data): void
    {
        [$refreshToken, $parentData] = $data;

        // The state comes back from the session, so it is not trusted to have the right shape
        if (!$refreshToken instanceof RefreshTokenInterface || !is_array($parentData)) {
            throw new UnexpectedValueException(sprintf('The serialized state of "%s" is not usable.', self::class));
        }

        $this->refreshToken = $refreshToken;

        parent::__unserialize($parentData);
    }
}
