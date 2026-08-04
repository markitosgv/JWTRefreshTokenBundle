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
        private readonly int $ttl,
        private readonly RequestStack $requestStack,
        private readonly string $tokenParameterName,
        private readonly bool $singleUse,
        private readonly RefreshTokenGeneratorInterface $refreshTokenGenerator,
        private readonly ExtractorInterface $extractor,
        array $cookieSettings,
        private readonly bool $returnExpiration = false,
        private readonly string $returnExpirationParameterName = 'refresh_token_expiration',
        private readonly bool $singleUseTtlUpdate = true
    ) {
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
    }

    /**
     * Seconds left before the token expires, never below zero: a replacement issued for no time at
     * all is the session having run out, not one lasting forever.
     */
    private function remainingTtl(RefreshTokenInterface $refreshToken): int
    {
        $valid = $refreshToken->getValid();

        if (null === $valid) {
            return 0;
        }

        return max(0, $valid->getTimestamp() - time());
    }

    public function attachRefreshToken(AuthenticationSuccessEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return;
        }

        $user = $event->getUser();
        $data = $event->getData();

        // Extract refreshToken from the request, treating an empty value as no value at all so the
        // checks below only have to care about null
        $refreshTokenString = $this->extractor->getRefreshToken($request, $this->tokenParameterName);

        if ('' === $refreshTokenString || '0' === $refreshTokenString) {
            $refreshTokenString = null;
        }

        // How long the token being replaced had left, read before it goes
        $remainingTtl = null;

        // Remove the current refreshToken if it is single-use
        if (null !== $refreshTokenString && true === $this->singleUse) {
            $refreshToken = $this->refreshTokenManager->get($refreshTokenString);
            $refreshTokenString = null;

            if ($refreshToken instanceof RefreshTokenInterface) {
                $remainingTtl = $this->remainingTtl($refreshToken);

                $this->refreshTokenManager->delete($refreshToken);
            }
        }

        // Set or create the refreshTokenString
        $issuedToken = null;

        if (null !== $refreshTokenString) {
            $data[$this->tokenParameterName] = $refreshTokenString;

            // Only read back when the expiry is going to be used, either in the body or on the
            // cookie
            if ($this->returnExpiration || $this->cookieSettings['enabled']) {
                $issuedToken = $this->refreshTokenManager->get($refreshTokenString);
            }

            if ($this->returnExpiration) {
                $data[$this->returnExpirationParameterName] = $issuedToken?->getValid()?->getTimestamp() ?? 0;
            }
        } else {
            // Starting the ttl over on every rotation means refreshing can be chained for as long
            // as the user keeps at it, so the replacement can be made to end when the token it
            // replaces would have
            $refreshToken = $this->refreshTokenGenerator->createForUserWithTtl(
                $user,
                $this->singleUseTtlUpdate || null === $remainingTtl ? $this->ttl : $remainingTtl
            );

            $this->refreshTokenManager->save($refreshToken);
            $issuedToken = $refreshToken;
            $refreshTokenString = $refreshToken->getRefreshToken();
            $data[$this->tokenParameterName] = $refreshTokenString;

            if ($this->returnExpiration) {
                $data[$this->returnExpirationParameterName] = $refreshToken->getValid()?->getTimestamp() ?? 0;
            }
        }

        // Add a response cookie if enabled
        if ($this->cookieSettings['enabled']) {
            $event->getResponse()->headers->setCookie(
                new Cookie(
                    $this->tokenParameterName,
                    $refreshTokenString,
                    // The cookie goes when the token does, which is not a ttl from now once the
                    // replacement inherits the expiry of the one it replaced
                    $issuedToken?->getValid()?->getTimestamp() ?? time() + $this->ttl,
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
            if ($this->cookieSettings['remove_token_from_body']) {
                unset($data[$this->tokenParameterName]);
            }
        }

        // Set response data
        $event->setData($data);
    }
}
