<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Entity;

use Deprecated;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\Column]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column]
        private string $email,
        #[ORM\Column(nullable: true)]
        private ?string $password = null
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoles(): array
    {
        return [];
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getSalt(): void
    {
    }

    #[Deprecated]
    public function eraseCredentials(): void
    {
    }

    public function getUsername(): string
    {
        return $this->getUserIdentifier();
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }
}
