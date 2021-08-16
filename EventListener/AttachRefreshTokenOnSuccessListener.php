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

    /**
     * @var array
     */
    protected $cookieSettings;

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
        array $cookieSettings
    ) {
        $this->refreshTokenManager = $refreshTokenManager;
        $this->ttl = $ttl;
        $this->requestStack = $requestStack;
        $this->tokenParameterName = $tokenParameterName;
        $this->singleUse = $singleUse;
        $this->refreshTokenGenerator = $refreshTokenGenerator;
        $this->extractor = $extractor;
        $this->cookieSettings = array_merge([
            'sameSite' => 'lax',
            'path' => '/',
            'domain' => null,
            'httpOnly' => true,
            'secure' => true,
        ], $cookieSettings);
    }

    public function attachRefreshToken(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof UserInterface) {
            return;
        }

        $data = $event->getData();
        $request = $this->requestStack->getCurrentRequest();

        $refreshTokenString = $this->extractor->getRefreshToken($request, $this->tokenParameterName);

        if ($refreshTokenString && true === $this->singleUse) {
            $refreshToken = $this->refreshTokenManager->get($refreshTokenString);
            $refreshTokenString = null;

            if ($refreshToken instanceof RefreshTokenInterface) {
                $this->refreshTokenManager->delete($refreshToken);
            }
        }

        if ($refreshTokenString) {
            $data[$this->tokenParameterName] = $refreshTokenString;
        } else {
            $refreshToken = $this->refreshTokenGenerator->createForUserWithTtl($user, $this->ttl);

            $this->refreshTokenManager->save($refreshToken);
            $refreshTokenString = $refreshToken->getRefreshToken();
        }

        if (isset($this->cookieSettings['enabled']) && $this->cookieSettings['enabled']) {
            $event->getResponse()->headers->setCookie(
                new Cookie(
                    $this->tokenParameterName,
                    $refreshTokenString,
                    time() + $this->ttl,
                    $this->cookieSettings['path'],
                    $this->cookieSettings['domain'],
                    $this->cookieSettings['secure'],
                    $this->cookieSettings['httpOnly'],
                    false,
                    $this->cookieSettings['sameSite']
                )
            );
        } else {
            $data[$this->tokenParameterName] = $refreshTokenString;

            $event->setData($data);
        }
    }
}
