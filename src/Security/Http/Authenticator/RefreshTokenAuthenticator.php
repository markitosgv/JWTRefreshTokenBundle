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

use DateTime;
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
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class RefreshTokenAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    /**
     * @var array{check_path: string, ttl: int, ttl_update: bool, token_parameter_name: string}
     */
    private array $options;

    /**
     * @param UserProviderInterface<UserInterface>                                                   $userProvider
     * @param array{check_path: string, ttl?: int, ttl_update?: bool, token_parameter_name?: string} $options
     *
     * @psalm-mutation-free
     */
    public function __construct(
        private readonly RefreshTokenManagerInterface $refreshTokenManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ExtractorInterface $extractor,
        private readonly UserProviderInterface $userProvider,
        private readonly AuthenticationSuccessHandlerInterface $successHandler,
        private readonly AuthenticationFailureHandlerInterface $failureHandler,
        array $options,
        private readonly HttpUtils $httpUtils
    ) {
        $this->options = array_merge([
            'ttl' => 2592000,
            'ttl_update' => false,
            'token_parameter_name' => 'refresh_token',
        ], $options);
    }

    #[\Override]
    public function supports(Request $request): bool
    {
        return $this->httpUtils->checkRequestPath($request, $this->options['check_path']);
    }

    #[\Override]
    public function authenticate(Request $request): Passport
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
            throw new InvalidTokenException(sprintf('Refresh token "%s" is invalid.', $token));
        }

        if ($this->options['ttl_update']) {
            $expirationDate = new DateTime();

            // Explicitly check for a negative number based on a behavior change in PHP 8.2, see https://github.com/php/php-src/issues/9950
            if ($this->options['ttl'] > 0) {
                $expirationDate->modify(sprintf('+%d seconds', $this->options['ttl']));
            } elseif ($this->options['ttl'] < 0) {
                $expirationDate->modify(sprintf('%d seconds', $this->options['ttl']));
            }

            $refreshToken->setValid($expirationDate);

            $this->refreshTokenManager->save($refreshToken);
        }

        $username = $refreshToken->getUsername();

        if (null === $username) {
            throw new InvalidTokenException(sprintf('Refresh token "%s" is not attached to a user.', $token));
        }

        $passport = new SelfValidatingPassport(new UserBadge($username, $this->userProvider->loadUserByIdentifier(...)));
        $passport->setAttribute('refreshToken', $refreshToken);

        return $passport;
    }

    #[\Override]
    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
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

    #[\Override]
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return $this->successHandler->onAuthenticationSuccess($request, $token);
    }

    #[\Override]
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return $this->failureHandler->onAuthenticationFailure($request, $exception);
    }

    #[\Override]
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        $response = new RefreshAuthenticationFailureResponse($authException instanceof AuthenticationException ? $authException->getMessageKey() : 'Authentication error');

        $event = new RefreshTokenNotFoundEvent(
            new MissingTokenException('JWT Refresh Token not found', 0, $authException),
            $response
        );

        $this->eventDispatcher->dispatch($event, 'gesdinet.refresh_token_not_found');

        // A listener is free to replace the response, but it may also clear it
        return $event->getResponse() ?? $response;
    }
}
