<?php

declare(strict_types=1);

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Event;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Contracts\EventDispatcher\Event;

final class RefreshTokenNotFoundEvent extends Event
{
    /**
     * @psalm-mutation-free
     */
    public function __construct(
        private readonly AuthenticationException $exception,
        private ?Response $response = null
    ) {
    }

    public function getException(): AuthenticationException
    {
        return $this->exception;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    /**
     * @psalm-external-mutation-free
     */
    public function setResponse(?Response $response = null): void
    {
        $this->response = $response;
    }
}
