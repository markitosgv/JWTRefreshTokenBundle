<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ODM\Document]
class User implements UserInterface
{
    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field]
    private string $email;

    #[ODM\Field(nullable: true)]
    private ?string $password;

    public function __construct(string $email, ?string $password = null)
    {
        $this->email = $email;
        $this->password = $password;
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
