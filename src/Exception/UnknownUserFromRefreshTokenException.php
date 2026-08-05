<?php

declare(strict_types=1);

namespace Gesdinet\JWTRefreshTokenBundle\Exception;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class UnknownUserFromRefreshTokenException extends AuthenticationException
{
}
