<?php

namespace Gesdinet\JWTRefreshTokenBundle\Exception;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

class InvalidRefreshTokenException extends AuthenticationException
{
}
