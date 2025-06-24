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

use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
final class AttachRefreshTokenOnSuccessListener
{
    private RefreshTokenManagerInterface $refreshTokenManager;

    private int $ttl;

    private RequestStack $requestStack;

    private string $tokenParameterName;

    private bool $singleUse;

    private RefreshTokenGeneratorInterface $refreshTokenGenerator;

    private ExtractorInterface $extractor;

    private array $cookieSettings;

    private bool $returnExpiration;

    private string $returnExpirationParameterName;

    public function __construct(
        RefreshTokenManagerInterface $refreshTokenManager,
        int $ttl,
        RequestStack $requestStack,
        string $tokenParameterName,
        bool $singleUse,
        RefreshTokenGeneratorInterface $refreshTokenGenerator,
        ExtractorInterface $extractor,
        array $cookieSettings,
        bool $returnExpiration = false,
        string $returnExpirationParameterName = 'refresh_token_expiration'
    ) {
        $this->refreshTokenManager = $refreshTokenManager;
        $this->ttl = $ttl;
        $this->requestStack = $requestStack;
        $this->tokenParameterName = $tokenParameterName;
        $this->singleUse = $singleUse;
        $this->refreshTokenGenerator = $refreshTokenGenerator;
        $this->extractor = $extractor;
        $this->cookieSettings = array_merge([
            'enabled' => false,
            'same_site' => 'lax',
            'path' => '/',
            'domain' => null,
            'http_only' => true,
            'secure' => true,
            'remove_token_from_body' => true,
            'partitioned' => false,
        ], $cookieSettings);
        $this->returnExpiration = $returnExpiration;
        $this->returnExpirationParameterName = $returnExpirationParameterName;
    }

    public function attachRefreshToken(AuthenticationSuccessEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return;
        }

        $user = $event->getUser();
        $data = $event->getData();

        // Extract refreshToken from the request
        $refreshTokenString = $this->extractor->getRefreshToken($request, $this->tokenParameterName);

        // Remove the current refreshToken if it is single-use
        if ($refreshTokenString && true === $this->singleUse) {
            $refreshToken = $this->refreshTokenManager->get($refreshTokenString);
            $refreshTokenString = null;

            if ($refreshToken instanceof RefreshTokenInterface) {
                $this->refreshTokenManager->delete($refreshToken);
            }
        }

        // Set or create the refreshTokenString
        if ($refreshTokenString) {
            $data[$this->tokenParameterName] = $refreshTokenString;

            if ($this->returnExpiration) {
                $refreshToken = $this->refreshTokenManager->get($refreshTokenString);
                $data[$this->returnExpirationParameterName] = ($refreshToken) ? $refreshToken->getValid()->getTimestamp() : 0;
            }
        } else {
            $refreshToken = $this->refreshTokenGenerator->createForUserWithTtl($user, $this->ttl);

            $this->refreshTokenManager->save($refreshToken);
            $refreshTokenString = $refreshToken->getRefreshToken();
            $data[$this->tokenParameterName] = $refreshTokenString;

            if ($this->returnExpiration) {
                $data[$this->returnExpirationParameterName] = $refreshToken->getValid()->getTimestamp();
            }
        }

        // Add a response cookie if enabled
        if ($this->cookieSettings['enabled']) {
            $event->getResponse()->headers->setCookie(
                new Cookie(
                    $this->tokenParameterName,
                    $refreshTokenString,
                    time() + $this->ttl,
                    $this->cookieSettings['path'],
                    $this->cookieSettings['domain'],
                    $this->cookieSettings['secure'],
                    $this->cookieSettings['http_only'],
                    false,
                    $this->cookieSettings['same_site'],
                    $this->cookieSettings['partitioned'],
                )
            );

            // Remove the refreshTokenString from the response body
            if (isset($this->cookieSettings['remove_token_from_body']) && $this->cookieSettings['remove_token_from_body']) {
                unset($data[$this->tokenParameterName]);
            }
        }

        // Set response data
        $event->setData($data);
    }
}
