<?php

namespace Gesdinet\JWTRefreshTokenBundle\Exception;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

class UnknownRefreshTokenException extends AuthenticationException
{
}
