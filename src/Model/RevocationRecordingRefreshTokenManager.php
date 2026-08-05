<?php

declare(strict_types=1);

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Model;

use DateTimeInterface;
use Gesdinet\JWTRefreshTokenBundle\Security\Revocation\JWTRevocationRegistryInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Notes the moment a user's sessions were revoked, so their JWTs can be refused too.
 *
 * `revokeAllForUser()` is what an application calls after a password reset or when disabling an
 * account, and on its own it only takes away the refresh tokens: every JWT already issued keeps
 * working until it expires, which is exactly the wrong outcome at exactly the moment it matters. The
 * mark left here is what {@see \Gesdinet\JWTRefreshTokenBundle\EventListener\RejectJWTsIssuedBeforeRevocationListener}
 * checks.
 *
 * Deliberately only that method. `revokeAllButNewestForUser()` prunes a user's older sessions while
 * they carry on using the newest, so marking there would sign them out of the device they are
 * holding. `revokeFamily()` ends one session, and nothing on a JWT says which chain issued it, so
 * there is no way to refuse only that session's tokens.
 */
final readonly class RevocationRecordingRefreshTokenManager implements FamilyRefreshTokenManagerInterface, ListRefreshTokenManagerInterface, RefreshTokenManagerInterface, RevokeRefreshTokenManagerInterface
{
    /**
     * @psalm-mutation-free
     */
    public function __construct(
        private RefreshTokenManagerInterface $manager,
        private JWTRevocationRegistryInterface $registry,
    ) {
    }

    #[\Override]
    public function revokeAllForUser(UserInterface $user): int
    {
        $revoked = $this->delegateOrFail(RevokeRefreshTokenManagerInterface::class)->revokeAllForUser($user);

        // Marked after the revocation rather than before: a mark left by a call that then failed
        // would sign the user out of everything without having revoked anything, and there would be
        // nothing to say why
        $this->registry->revokeIssuedBefore($user->getUserIdentifier(), new \DateTimeImmutable());

        return $revoked;
    }

    #[\Override]
    public function revokeAllButNewestForUser(UserInterface $user, int $keep): int
    {
        return $this->delegateOrFail(RevokeRefreshTokenManagerInterface::class)->revokeAllButNewestForUser($user, $keep);
    }

    #[\Override]
    public function revokeFamily(string $family): int
    {
        return $this->delegateOrFail(FamilyRefreshTokenManagerInterface::class)->revokeFamily($family);
    }

    #[\Override]
    public function findAllForUser(UserInterface $user): array
    {
        return $this->delegateOrFail(ListRefreshTokenManagerInterface::class)->findAllForUser($user);
    }

    #[\Override]
    public function get(string $refreshToken): ?RefreshTokenInterface
    {
        return $this->manager->get($refreshToken);
    }

    #[\Override]
    public function getLastFromUsername(string $username): ?RefreshTokenInterface
    {
        return $this->manager->getLastFromUsername($username);
    }

    #[\Override]
    public function save(RefreshTokenInterface $refreshToken, bool $andFlush = true): void
    {
        $this->manager->save($refreshToken);
    }

    #[\Override]
    public function delete(RefreshTokenInterface $refreshToken, bool $andFlush = true): int
    {
        return $this->manager->delete($refreshToken, $andFlush);
    }

    #[\Override]
    public function revokeAllInvalid(?DateTimeInterface $datetime = null, bool $andFlush = true): array
    {
        return $this->manager->revokeAllInvalid($datetime, $andFlush);
    }

    #[\Override]
    public function revokeAllInvalidBatch(?DateTimeInterface $datetime = null, ?int $batchSize = null, int $offset = 0, bool $andFlush = true): array
    {
        return $this->manager->revokeAllInvalidBatch($datetime, $batchSize, $offset, $andFlush);
    }

    /**
     * @psalm-mutation-free
     */
    #[\Override]
    public function getClass(): string
    {
        return $this->manager->getClass();
    }

    /**
     * The manager underneath may implement only RefreshTokenManagerInterface, which this one cannot
     * make up for. Saying so beats a call to a method that is not there.
     *
     * @template T of object
     *
     * @param class-string<T> $interface
     *
     * @return T
     *
     * @psalm-mutation-free
     */
    private function delegateOrFail(string $interface): object
    {
        if (!$this->manager instanceof $interface) {
            throw new \LogicException(sprintf('The refresh token manager being decorated, "%s", does not implement "%s".', get_debug_type($this->manager), $interface));
        }

        return $this->manager;
    }
}
