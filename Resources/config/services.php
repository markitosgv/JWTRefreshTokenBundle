<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gesdinet\JWTRefreshTokenBundle\Command\ClearInvalidRefreshTokensCommand;
use Gesdinet\JWTRefreshTokenBundle\Command\RevokeRefreshTokenCommand;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\EventListener\AttachRefreshTokenOnSuccessListener;
use Gesdinet\JWTRefreshTokenBundle\EventListener\LogoutEventListener;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGenerator;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ChainExtractor;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\RequestBodyExtractor;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\RequestParameterExtractor;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\RequestCookieExtractor;
use Gesdinet\JWTRefreshTokenBundle\Security\Http\Authentication\AuthenticationFailureHandler;
use Gesdinet\JWTRefreshTokenBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Gesdinet\JWTRefreshTokenBundle\Security\Authenticator\RefreshTokenAuthenticator as LegacyRefreshTokenAuthenticator;
use Gesdinet\JWTRefreshTokenBundle\Security\Http\Authenticator\RefreshTokenAuthenticator;
use Gesdinet\JWTRefreshTokenBundle\Security\Provider\RefreshTokenProvider;
use Gesdinet\JWTRefreshTokenBundle\Service\RefreshToken;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('gesdinet.jwtrefreshtoken.send_token')
        ->class(AttachRefreshTokenOnSuccessListener::class)
        ->args([
            service('gesdinet.jwtrefreshtoken.refresh_token_manager'),
            param('gesdinet_jwt_refresh_token.ttl'),
            service('request_stack'),
            param('gesdinet_jwt_refresh_token.token_parameter_name'),
            param('gesdinet_jwt_refresh_token.single_use'),
            service('gesdinet.jwtrefreshtoken.refresh_token_generator'),
            service('gesdinet.jwtrefreshtoken.request.extractor.chain'),
            param('gesdinet_jwt_refresh_token.cookie'),
            param('gesdinet_jwt_refresh_token.return_expiration'),
            param('gesdinet_jwt_refresh_token.return_expiration_parameter_name'),
        ])
        ->tag('kernel.event_listener', [
            'event' => 'lexik_jwt_authentication.on_authentication_success',
            'method' => 'attachRefreshToken',
        ]);

    $services->set('gesdinet.jwtrefreshtoken.refresh_token_generator')
        ->class(RefreshTokenGenerator::class)
        ->public()
        ->args([
            service('gesdinet.jwtrefreshtoken.refresh_token_manager'),
        ]);

    $services->alias(RefreshTokenGeneratorInterface::class, 'gesdinet.jwtrefreshtoken.refresh_token_generator');

    $services->set('gesdinet.jwtrefreshtoken.refresh_token_manager')
        ->class(RefreshTokenManager::class)
        ->public()
        ->args([
            service('gesdinet.jwtrefreshtoken.object_manager'),
            param('gesdinet.jwtrefreshtoken.refresh_token.class'),
        ]);

    $services->alias(RefreshTokenManagerInterface::class, 'gesdinet.jwtrefreshtoken.refresh_token_manager');

    $services->set('gesdinet.jwtrefreshtoken.request.extractor.chain')
        ->class(ChainExtractor::class)
        ->public();

    $services->alias(ExtractorInterface::class, 'gesdinet.jwtrefreshtoken.request.extractor.chain');

    $services->set('gesdinet.jwtrefreshtoken.request.extractor.request_body')
        ->class(RequestBodyExtractor::class)
        ->tag('gesdinet_jwt_refresh_token.request_extractor');

    $services->set('gesdinet.jwtrefreshtoken.request.extractor.request_parameter')
        ->class(RequestParameterExtractor::class)
        ->tag('gesdinet_jwt_refresh_token.request_extractor');

    $services->set('gesdinet.jwtrefreshtoken.request.extractor.request_cookie')
        ->class(RequestCookieExtractor::class)
        ->tag('gesdinet_jwt_refresh_token.request_extractor');

    $services->set('gesdinet.jwtrefreshtoken')
        ->deprecate('gesdinet/jwt-refresh-token-bundle', '1.0', 'The "%service_id%" service is deprecated.')
        ->class(RefreshToken::class)
        ->public()
        ->args([
            service('gesdinet.jwtrefreshtoken.authenticator'),
            service('gesdinet.jwtrefreshtoken.user_provider'),
            service('lexik_jwt_authentication.handler.authentication_success'),
            service('lexik_jwt_authentication.handler.authentication_failure'),
            service('gesdinet.jwtrefreshtoken.refresh_token_manager'),
            param('gesdinet_jwt_refresh_token.ttl'),
            param('gesdinet_jwt_refresh_token.security.firewall'),
            param('gesdinet_jwt_refresh_token.ttl_update'),
            service('event_dispatcher'),
        ]);

    $services->set('gesdinet.jwtrefreshtoken.user_provider')
        ->deprecate('gesdinet/jwt-refresh-token-bundle', '1.0', 'The "%service_id%" service is deprecated.')
        ->class(RefreshTokenProvider::class)
        ->args([
            service('gesdinet.jwtrefreshtoken.refresh_token_manager'),
        ]);

    $services->set('gesdinet.jwtrefreshtoken.authenticator')
        ->deprecate('gesdinet/jwt-refresh-token-bundle', '1.0', 'The "%service_id%" service is deprecated.')
        ->class(LegacyRefreshTokenAuthenticator::class)
        ->args([
            service('gesdinet.jwtrefreshtoken.user_checker'),
            param('gesdinet_jwt_refresh_token.token_parameter_name'),
            service('gesdinet.jwtrefreshtoken.request.extractor.chain'),
        ]);

    $services->set('gesdinet.jwtrefreshtoken.security.authentication.failure_handler')
        ->class(AuthenticationFailureHandler::class)
        ->args([
            service('event_dispatcher'),
        ]);

    $services->set('gesdinet.jwtrefreshtoken.security.authentication.success_handler')
        ->class(AuthenticationSuccessHandler::class)
        ->args([
            service('lexik_jwt_authentication.handler.authentication_success'),
            service('event_dispatcher'),
        ]);

    $services->set('gesdinet.jwtrefreshtoken.security.refresh_token_authenticator')
        ->abstract()
        ->class(RefreshTokenAuthenticator::class)
        ->args([
            service('gesdinet.jwtrefreshtoken.refresh_token_manager'),
            service('event_dispatcher'),
            service('gesdinet.jwtrefreshtoken.request.extractor.chain'),
            abstract_arg('user provider'),
            abstract_arg('authentication success handler'),
            abstract_arg('authentication failure handler'),
            abstract_arg('options'),
            service('security.http_utils'),
        ]);

    $services->set(ClearInvalidRefreshTokensCommand::class)
        ->args([
            service('gesdinet.jwtrefreshtoken.refresh_token_manager'),
        ])
        ->tag('console.command');

    $services->set(RevokeRefreshTokenCommand::class)
        ->args([
            service('gesdinet.jwtrefreshtoken.refresh_token_manager'),
        ])
        ->tag('console.command');

    $services->set(LogoutEventListener::class)
        ->args([
            service('gesdinet.jwtrefreshtoken.refresh_token_manager'),
            service('gesdinet.jwtrefreshtoken.request.extractor.chain'),
            param('gesdinet_jwt_refresh_token.token_parameter_name'),
            param('gesdinet_jwt_refresh_token.cookie'),
            param('gesdinet_jwt_refresh_token.logout_firewall_context'),
        ])
        ->tag('kernel.event_listener', [
            'event' => 'Symfony\Component\Security\Http\Event\LogoutEvent',
            'method' => 'onLogout',
        ]);
};
