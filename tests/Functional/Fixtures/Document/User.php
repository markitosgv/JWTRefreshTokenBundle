<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Document;

use Deprecated;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ODM\Document]
class User implements UserInterface
{
    #[ODM\Id]
    private ?string $id = null;

    public function __construct(
        #[ODM\Field]
        private string $email,
        #[ODM\Field(nullable: true)]
        private ?string $password = null
    ) {
    }

    public function getId(): ?string
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
