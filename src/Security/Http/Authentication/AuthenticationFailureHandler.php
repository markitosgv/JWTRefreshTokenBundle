<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Security\Http\Authentication;

use Gesdinet\JWTRefreshTokenBundle\Event\RefreshAuthenticationFailureEvent;
use Gesdinet\JWTRefreshTokenBundle\Http\RefreshAuthenticationFailureResponse;
use Gesdinet\JWTRefreshTokenBundle\Security\Exception\TooManyRefreshRequestsException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class AuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    /**
     * @psalm-mutation-free
     */
    public function __construct(private EventDispatcherInterface $eventDispatcher)
    {
    }

    #[\Override]
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $event = new RefreshAuthenticationFailureEvent($exception, $this->responseFor($exception));

        $this->eventDispatcher->dispatch($event, 'gesdinet.refresh_token_failure');

        return $event->getResponse();
    }

    /**
     * Being refused for asking too often is not a credentials problem, and answering 401 would tell
     * a client to go and get new ones — which is more requests, at the endpoint already saying it
     * has had too many.
     */
    private function responseFor(AuthenticationException $exception): RefreshAuthenticationFailureResponse
    {
        if (!$exception instanceof TooManyRefreshRequestsException) {
            return new RefreshAuthenticationFailureResponse($exception->getMessageKey());
        }

        $response = new RefreshAuthenticationFailureResponse($exception->getMessageKey(), Response::HTTP_TOO_MANY_REQUESTS);

        $retryAfter = $exception->getRetryAfter();

        if (null !== $retryAfter) {
            // Seconds rather than a date: both are allowed, and a client with a clock that disagrees
            // with the server's gets the delay right either way
            $response->headers->set('Retry-After', (string) max(0, $retryAfter->getTimestamp() - time()));
        }

        return $response;
    }
}
