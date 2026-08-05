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

namespace Gesdinet\JWTRefreshTokenBundle\Security\Exception;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * The refresh endpoint was asked more often than the configured limiter allows.
 *
 * Unlike the other failures here this one says nothing about the token: it is refused before the
 * storage is consulted, so a caller cannot learn from the timing whether the token existed.
 */
final class TooManyRefreshRequestsException extends AuthenticationException
{
    private ?\DateTimeImmutable $retryAfter = null;

    /**
     * When the caller may try again, so the response can say so rather than leaving them to guess.
     *
     * @psalm-mutation-free
     */
    public function getRetryAfter(): ?\DateTimeImmutable
    {
        return $this->retryAfter;
    }

    /**
     * @psalm-external-mutation-free
     */
    public function setRetryAfter(\DateTimeImmutable $retryAfter): self
    {
        $this->retryAfter = $retryAfter;

        return $this;
    }

    /**
     * @psalm-pure
     */
    #[\Override]
    public function getMessageKey(): string
    {
        return 'Too many refresh requests, please try again later.';
    }
}
