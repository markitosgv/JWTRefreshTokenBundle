<?php

declare(strict_types=1);

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\BareEntityRepository;

/**
 * A token mapped the way an application with a repository of its own maps one.
 */
#[ORM\Entity(repositoryClass: BareEntityRepository::class)]
#[ORM\Table(name: 'bare_refresh_tokens')]
class BareRepositoryRefreshToken extends BaseRefreshToken
{
}
