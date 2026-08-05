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
 * Storage for {@see FamilyAwareRefreshTokenInterface}, for a token class with no reason to hold the
 * value differently. The Entity and Document tokens that ship with the bundle use it.
 *
 * A token class of your own that extends neither still opts in by using this trait, implementing the
 * interface and mapping the property. Mapping it is the part that matters: an unmapped property
 * would satisfy the interface and lose the family on every read, which is worse than not having it.
 */
trait RefreshTokenFamilyTrait
{
    protected ?string $family = null;

    protected ?\DateTimeInterface $familyValid = null;

    public function getFamily(): ?string
    {
        return $this->family;
    }

    public function getFamilyValid(): ?\DateTimeInterface
    {
        return $this->familyValid;
    }

    /**
     * @psalm-external-mutation-free
     */
    public function setFamilyValid(\DateTimeInterface $familyValid): static
    {
        $this->familyValid = $familyValid;

        return $this;
    }

    /**
     * @psalm-external-mutation-free
     */
    public function setFamily(string $family): static
    {
        $this->family = $family;

        return $this;
    }
}
