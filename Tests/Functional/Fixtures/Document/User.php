<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ODM\Document
 */
class User implements UserInterface
{
    /**
     * @var int|null
     *
     * @ODM\Id
     */
    private $id;

    /**
     * @var string
     *
     * @ODM\Field
     */
    private $email;

    /**
     * @var string|null
     *
     * @ODM\Field(nullable=true)
     */
    private $password;

    public function __construct(string $email, ?string $password = null)
    {
        $this->email = $email;
        $this->password = $password;
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
