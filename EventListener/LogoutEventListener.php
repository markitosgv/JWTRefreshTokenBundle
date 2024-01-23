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

final class LogoutEventListener
{
    private RefreshTokenManagerInterface $refreshTokenManager;
    private ExtractorInterface $refreshTokenExtractor;
    private string $tokenParameterName;
    private array $cookieSettings;

    public function __construct(
        RefreshTokenManagerInterface $refreshTokenManager,
        ExtractorInterface $refreshTokenExtractor,
        string $tokenParameterName,
        array $cookieSettings,
    ) {
        $this->refreshTokenManager = $refreshTokenManager;
        $this->refreshTokenExtractor = $refreshTokenExtractor;
        $this->tokenParameterName = $tokenParameterName;
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
            $event->setResponse(
                new JsonResponse(
                    [
                        'code' => 200,
                        'message' => 'The supplied refresh_token is already invalid.',
                    ],
                    Response::HTTP_OK
                )
            );
        } else {
            $this->refreshTokenManager->delete($refreshToken);
            $event->setResponse(
                new JsonResponse(
                    [
                        'code' => 200,
                        'message' => 'The supplied refresh_token has been invalidated.',
                    ],
                    Response::HTTP_OK
                )
            );
        }

        if ($this->cookieSettings['enabled']) {
            $response = $event->getResponse();
            $response->headers->clearCookie(
                $this->tokenParameterName,
                $this->cookieSettings['path'],
                $this->cookieSettings['domain'],
                $this->cookieSettings['secure'],
                $this->cookieSettings['http_only'],
                $this->cookieSettings['same_site']
            );
        }
    }
}
