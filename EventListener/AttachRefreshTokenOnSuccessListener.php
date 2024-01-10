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
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Kernel;

class AttachRefreshTokenOnSuccessListener
{
    /**
     * @var RefreshTokenManagerInterface
     */
    protected $refreshTokenManager;

    /**
     * @var int
     */
    protected $ttl;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var string
     */
    protected $tokenParameterName;

    /**
     * @var bool
     */
    protected $singleUse;

    /**
     * @var RefreshTokenGeneratorInterface
     */
    protected $refreshTokenGenerator;

    /**
     * @var ExtractorInterface
     */
    protected $extractor;

    protected array $cookieSettings;

    protected bool $returnExpiration;

    protected string $returnExpirationParameterName;

    /**
     * @param int    $ttl
     * @param string $tokenParameterName
     * @param bool   $singleUse
     */
    public function __construct(
        RefreshTokenManagerInterface $refreshTokenManager,
        $ttl,
        RequestStack $requestStack,
        $tokenParameterName,
        $singleUse,
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

        if ($this->cookieSettings['partitioned'] && Kernel::VERSION < '6.4') {
            throw new \LogicException(sprintf('The `partitioned` option for cookies is only available for Symfony 6.4 and above. You are currently on version %s', Kernel::VERSION));
        }
    }

    public function attachRefreshToken(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof UserInterface) {
            return;
        }

        $data = $event->getData();
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return;
        }

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
