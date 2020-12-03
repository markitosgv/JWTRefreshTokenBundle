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

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Request\RequestRefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Service\RefreshTokenInterface as RefreshTokenServiceInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AttachRefreshTokenOnSuccessListener
{
    /**
     * @var RefreshTokenManagerInterface
     */
    protected $refreshTokenManager;

    /**
     * @var RefreshTokenServiceInterface
     */
    protected $refreshTokenService;

    /**
     * @var ValidatorInterface
     */
    protected $validator;

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
     * AttachRefreshTokenOnSuccessListener constructor.
     *
     * @param string $userIdentityField
     * @param string $tokenParameterName
     * @param bool   $singleUse
     */
    public function __construct(
        RefreshTokenManagerInterface $refreshTokenManager,
        RefreshTokenServiceInterface $refreshTokenService,
        ValidatorInterface $validator,
        RequestStack $requestStack,
        $tokenParameterName,
        $singleUse
    ) {
        $this->refreshTokenManager = $refreshTokenManager;
        $this->refreshTokenService = $refreshTokenService;
        $this->validator = $validator;
        $this->requestStack = $requestStack;
        $this->tokenParameterName = $tokenParameterName;
        $this->singleUse = $singleUse;
    }

    public function attachRefreshToken(AuthenticationSuccessEvent $event)
    {
        $data = $event->getData();
        $user = $event->getUser();
        $request = $this->requestStack->getCurrentRequest();

        if (!$user instanceof UserInterface) {
            return;
        }

        $refreshTokenString = RequestRefreshToken::getRefreshToken($request, $this->tokenParameterName);

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
            $refreshToken = $this->refreshTokenService->create($user);
            $this->refreshTokenManager->save($refreshToken);
            $data[$this->tokenParameterName] = $refreshToken->getRefreshToken();
        }

        $event->setData($data);
    }
}
