<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Security\Http\Authenticator;

use Gesdinet\JWTRefreshTokenBundle\Event\RefreshTokenNotFoundEvent;
use Gesdinet\JWTRefreshTokenBundle\Http\RefreshAuthenticationFailureResponse;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use Gesdinet\JWTRefreshTokenBundle\Security\Exception\InvalidTokenException;
use Gesdinet\JWTRefreshTokenBundle\Security\Exception\MissingTokenException;
use Gesdinet\JWTRefreshTokenBundle\Security\Exception\TokenNotFoundException;
use Gesdinet\JWTRefreshTokenBundle\Security\Http\Authenticator\Token\PostRefreshTokenAuthenticationToken;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\LogicException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\UserPassportInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class RefreshTokenAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private RefreshTokenManagerInterface $refreshTokenManager;

    private EventDispatcherInterface $eventDispatcher;

    private ExtractorInterface $extractor;

    private UserProviderInterface $userProvider;

    private AuthenticationSuccessHandlerInterface $successHandler;

    private AuthenticationFailureHandlerInterface $failureHandler;

    private array $options;

    public function __construct(
        RefreshTokenManagerInterface $refreshTokenManager,
        EventDispatcherInterface $eventDispatcher,
        ExtractorInterface $extractor,
        UserProviderInterface $userProvider,
        AuthenticationSuccessHandlerInterface $successHandler,
        AuthenticationFailureHandlerInterface $failureHandler,
        array $options
    ) {
        $this->refreshTokenManager = $refreshTokenManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->extractor = $extractor;
        $this->userProvider = $userProvider;
        $this->successHandler = $successHandler;
        $this->failureHandler = $failureHandler;
        $this->options = array_merge([
            'ttl' => 2592000,
            'ttl_update' => false,
            'token_parameter_name' => 'refresh_token',
        ], $options);
    }

    public function supports(Request $request): bool
    {
        return null !== $this->extractor->getRefreshToken($request, $this->options['token_parameter_name']);
    }

    public function authenticate(Request $request): PassportInterface
    {
        $token = $this->extractor->getRefreshToken($request, $this->options['token_parameter_name']);

        if (null === $token) {
            throw new MissingTokenException();
        }

        $refreshToken = $this->refreshTokenManager->get($token);

        if (null === $refreshToken) {
            throw new TokenNotFoundException();
        }

        if (!$refreshToken->isValid()) {
            throw new InvalidTokenException(sprintf('Refresh token "%s" is invalid.', $refreshToken->getRefreshToken()));
        }

        if ($this->options['ttl_update']) {
            $expirationDate = new \DateTime();
            $expirationDate->modify(sprintf('+%d seconds', $this->options['ttl']));
            $refreshToken->setValid($expirationDate);

            $this->refreshTokenManager->save($refreshToken);
        }

        $method = method_exists($this->userProvider, 'loadUserByIdentifier') ? 'loadUserByIdentifier' : 'loadUserByUsername';

        $passport = new SelfValidatingPassport(new UserBadge($refreshToken->getUsername(), [$this->userProvider, $method]));
        $passport->setAttribute('refreshToken', $refreshToken);

        return $passport;
    }

    public function createAuthenticatedToken(PassportInterface $passport, string $firewallName): TokenInterface
    {
        if (!$passport instanceof UserPassportInterface) {
            throw new LogicException(sprintf('Passport does not contain a user, overwrite "createAuthenticatedToken()" in "%s" to create a custom authenticated token.', static::class));
        }

        /** @var RefreshTokenInterface|null $refreshToken */
        $refreshToken = $passport->getAttribute('refreshToken');

        if (null === $refreshToken) {
            throw new LogicException('Passport does not contain the refresh token.');
        }

        return new PostRefreshTokenAuthenticationToken(
            $passport->getUser(),
            $firewallName,
            $passport->getUser()->getRoles(),
            $refreshToken
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return $this->successHandler->onAuthenticationSuccess($request, $token);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return $this->failureHandler->onAuthenticationFailure($request, $exception);
    }

    public function start(Request $request, AuthenticationException $authException = null): ?Response
    {
        $event = new RefreshTokenNotFoundEvent(
            new MissingTokenException('JWT Refresh Token not found', 0, $authException),
            new RefreshAuthenticationFailureResponse($authException->getMessageKey())
        );

        $this->eventDispatcher->dispatch($event, 'gesdinet.refresh_token_not_found');

        return $event->getResponse();
    }
}
