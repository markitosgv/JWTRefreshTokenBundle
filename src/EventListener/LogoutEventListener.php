<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\EventListener;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * @internal
 */
final class LogoutEventListener
{
    /**
     * @var array{enabled: bool, same_site: 'lax'|'none'|'strict', path: string, domain: string|null, http_only: bool, secure: bool, partitioned: bool, remove_token_from_body: bool}
     */
    private array $cookieSettings;

    /**
     * @param array{enabled?: bool, same_site?: 'lax'|'none'|'strict', path?: string, domain?: string|null, http_only?: bool, secure?: bool, partitioned?: bool, remove_token_from_body?: bool} $cookieSettings
     *
     * @psalm-mutation-free
     */
    public function __construct(
        private readonly RefreshTokenManagerInterface $refreshTokenManager,
        private readonly ExtractorInterface $refreshTokenExtractor,
        private readonly string $tokenParameterName,
        array $cookieSettings
    ) {
        $this->cookieSettings = array_merge([
            'enabled' => false,
            'same_site' => 'lax',
            'path' => '/',
            'domain' => null,
            'http_only' => true,
            'secure' => true,
            'partitioned' => false,
            'remove_token_from_body' => true,
        ], $cookieSettings);
    }

    public function onLogout(LogoutEvent $event): void
    {
        $request = $event->getRequest();

        $tokenString = $this->refreshTokenExtractor->getRefreshToken($request, $this->tokenParameterName);

        if (null === $tokenString) {
            $event->setResponse(
                new JsonResponse(
                    [
                        'code' => 400,
                        'message' => 'No refresh_token found.',
                    ],
                    Response::HTTP_BAD_REQUEST
                )
            );

            return;
        }

        $refreshToken = $this->refreshTokenManager->get($tokenString);

        if (null === $refreshToken) {
            $response = new JsonResponse(
                [
                    'code' => 200,
                    'message' => 'The supplied refresh_token is already invalid.',
                ],
                Response::HTTP_OK
            );
        } else {
            $this->refreshTokenManager->delete($refreshToken);
            $response = new JsonResponse(
                [
                    'code' => 200,
                    'message' => 'The supplied refresh_token has been invalidated.',
                ],
                Response::HTTP_OK
            );
        }

        $event->setResponse($response);

        if ($this->cookieSettings['enabled']) {
            $response->headers->clearCookie(
                $this->tokenParameterName,
                $this->cookieSettings['path'],
                $this->cookieSettings['domain'],
                $this->cookieSettings['secure'],
                $this->cookieSettings['http_only'],
                $this->cookieSettings['same_site'],
                $this->cookieSettings['partitioned']
            );
        }
    }
}
