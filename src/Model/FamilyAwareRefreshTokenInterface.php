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
 * A refresh token that knows which chain of refreshes it belongs to.
 *
 * A token issued in place of another carries the family of the one it replaced, so a login and every
 * refresh descending from it share one value. That is what makes a session addressable. Without it
 * the bundle holds a set of tokens with nothing relating them, and "end this session" can only mean
 * "delete this one token" — which, if the client has refreshed since, is a token that no longer
 * exists while the session it belonged to carries on.
 *
 * Kept apart from RefreshTokenInterface so that a token class written against that interface keeps
 * working untouched. A token class that does not implement this one simply has no families, and the
 * features built on them say so rather than appearing to work.
 *
 * @psalm-mutable
 */
interface FamilyAwareRefreshTokenInterface extends RefreshTokenInterface
{
    /**
     * The chain this token belongs to.
     *
     * Null for a token stored before the token class gained families, which is why every reader has
     * to allow for it rather than assume the column is populated.
     *
     * @psalm-mutation-free
     */
    public function getFamily(): ?string;

    /**
     * @psalm-external-mutation-free
     */
    public function setFamily(string $family): static;

    /**
     * When the chain itself runs out, whatever the token's own expiry says.
     *
     * A ttl that starts over on every rotation means refreshing can be chained indefinitely: the
     * client keeps a session alive forever by using it. This is the ceiling on that, and it is
     * carried along the chain unchanged so it means the same at the hundredth refresh as the first.
     *
     * Null when no ceiling was configured, which is the default and how the bundle has always
     * behaved.
     *
     * @psalm-mutation-free
     */
    public function getFamilyValid(): ?\DateTimeInterface;

    /**
     * @psalm-external-mutation-free
     */
    public function setFamilyValid(\DateTimeInterface $familyValid): static;
}
