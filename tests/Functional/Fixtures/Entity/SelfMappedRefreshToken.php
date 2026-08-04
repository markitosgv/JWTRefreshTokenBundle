<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Entity;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository;
use Gesdinet\JWTRefreshTokenBundle\Model\AbstractRefreshToken;

/**
 * A token carrying its own mapping rather than inheriting the bundle's.
 *
 * The shipped `Entity\RefreshToken` is a mapped superclass that declares the identifier and its
 * AUTO strategy, and a subclass cannot override an identifier it inherits. Extending the model
 * instead leaves the mapping entirely to the application, which is how the strategy gets chosen:
 * SEQUENCE on PostgreSQL, IDENTITY here, since SQLite has no sequences.
 */
#[ORM\Entity(repositoryClass: RefreshTokenRepository::class)]
#[ORM\Table(name: 'self_mapped_refresh_tokens')]
class SelfMappedRefreshToken extends AbstractRefreshToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    protected int|string|null $id = null;

    #[ORM\Column(name: 'refresh_token', type: 'string', length: 128, unique: true)]
    protected ?string $refreshToken = null;

    #[ORM\Column(type: 'string', length: 255)]
    protected ?string $username = null;

    #[ORM\Column(type: 'datetime')]
    protected ?DateTimeInterface $valid = null;
}
