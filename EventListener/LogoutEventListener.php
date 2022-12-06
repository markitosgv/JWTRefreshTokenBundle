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
use Symfony\Component\Security\Http\Event\LogoutEvent;

class LogoutEventListener
{
    private RefreshTokenManagerInterface $refreshTokenManager;
    private ExtractorInterface $refreshTokenExtractor;
    private string $tokenParameterName;
    private array $cookieSettings;

    /**
     * @deprecated to be removed in 2.0
     */
    private ?string $logoutFirewallContext;

    public function __construct(
        RefreshTokenManagerInterface $refreshTokenManager,
        ExtractorInterface $refreshTokenExtractor,
        string $tokenParameterName,
        array $cookieSettings,
        ?string $logoutFirewallContext = null
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
        $this->logoutFirewallContext = $logoutFirewallContext;

        if (null !== $logoutFirewallContext) {
            trigger_deprecation('gesdinet/jwt-refresh-token-bundle', '1.5', 'Passing the logout firewall context to "%s" is deprecated and will not be supported in 2.0.', self::class);
        }
    }

    public function onLogout(LogoutEvent $event): void
    {
        $request = $event->getRequest();

        /*
         * This listener should only act in one of two conditions:
         *
         * 1) If the firewall context is not configured (this implies the listener is registered to the firewall specific event dispatcher)
         * 2) (Deprecated) The request's firewall context matches the configured firewall context
         */
        if (null !== $this->logoutFirewallContext && $request->attributes->get('_firewall_context') !== $this->logoutFirewallContext) {
            return;
        }

        $tokenString = $this->refreshTokenExtractor->getRefreshToken($request, $this->tokenParameterName);
        if (null === $tokenString) {
            $event->setResponse(
                new JsonResponse(
                    [
                        'code' => 400,
                        'message' => 'No refresh_token found.',
                    ],
                    JsonResponse::HTTP_BAD_REQUEST
                )
            );

            return;
        } else {
            $refreshToken = $this->refreshTokenManager->get($tokenString);
            if (null === $refreshToken) {
                $event->setResponse(
                    new JsonResponse(
                        [
                            'code' => 200,
                            'message' => 'The supplied refresh_token is already invalid.',
                        ],
                        JsonResponse::HTTP_OK
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
                        JsonResponse::HTTP_OK
                    )
                );
            }
        }

        if ($this->cookieSettings['enabled']) {
            $response = $event->getResponse();
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
