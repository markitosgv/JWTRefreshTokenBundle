<?php

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
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerConfigurator $container) {
    $deprecateArgs = static function (string $version, string $message = 'The "%service_id%" service is deprecated.'): array {
        if (method_exists(Definition::class, 'getDeprecation')) {
            return ['gesdinet/jwt-refresh-token-bundle', $version, $message];
        }

        return [$message];
    };

    $abstractArg = static function (string $description) {
        if (function_exists('Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg')) {
            return \Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg($description);
        }

        return null;
    };

    $services = $container->services();

    $services->set('gesdinet.jwtrefreshtoken.send_token')
        ->class(AttachRefreshTokenOnSuccessListener::class)
        ->args([
            new Reference('gesdinet.jwtrefreshtoken.refresh_token_manager'),
            new Parameter('gesdinet_jwt_refresh_token.ttl'),
            new Reference('request_stack'),
            new Parameter('gesdinet_jwt_refresh_token.token_parameter_name'),
            new Parameter('gesdinet_jwt_refresh_token.single_use'),
            new Reference('gesdinet.jwtrefreshtoken.refresh_token_generator'),
            new Reference('gesdinet.jwtrefreshtoken.request.extractor.chain'),
            new Parameter('gesdinet_jwt_refresh_token.cookie'),
            new Parameter('gesdinet_jwt_refresh_token.return_expiration'),
            new Parameter('gesdinet_jwt_refresh_token.return_expiration_parameter_name'),
        ])
        ->tag('kernel.event_listener', [
            'event' => 'lexik_jwt_authentication.on_authentication_success',
            'method' => 'attachRefreshToken',
        ]);

    $services->set('gesdinet.jwtrefreshtoken.refresh_token_generator')
        ->class(RefreshTokenGenerator::class)
        ->public()
        ->args([
            new Reference('gesdinet.jwtrefreshtoken.refresh_token_manager'),
        ]);

    $services->alias(RefreshTokenGeneratorInterface::class, 'gesdinet.jwtrefreshtoken.refresh_token_generator');

    $services->set('gesdinet.jwtrefreshtoken.refresh_token_manager')
        ->class(RefreshTokenManager::class)
        ->public()
        ->args([
            new Reference('gesdinet.jwtrefreshtoken.object_manager'),
            new Parameter('gesdinet.jwtrefreshtoken.refresh_token.class'),
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
        ->deprecate(...$deprecateArgs('1.0'))
        ->class(RefreshToken::class)
        ->public()
        ->args([
            new Reference('gesdinet.jwtrefreshtoken.authenticator'),
            new Reference('gesdinet.jwtrefreshtoken.user_provider'),
            new Reference('lexik_jwt_authentication.handler.authentication_success'),
            new Reference('lexik_jwt_authentication.handler.authentication_failure'),
            new Reference('gesdinet.jwtrefreshtoken.refresh_token_manager'),
            new Parameter('gesdinet_jwt_refresh_token.ttl'),
            new Parameter('gesdinet_jwt_refresh_token.security.firewall'),
            new Parameter('gesdinet_jwt_refresh_token.ttl_update'),
            new Reference('event_dispatcher'),
        ]);

    $services->set('gesdinet.jwtrefreshtoken.user_provider')
        ->deprecate(...$deprecateArgs('1.0'))
        ->class(RefreshTokenProvider::class)
        ->args([
            new Reference('gesdinet.jwtrefreshtoken.refresh_token_manager'),
        ]);

    $services->set('gesdinet.jwtrefreshtoken.authenticator')
        ->deprecate(...$deprecateArgs('1.0'))
        ->class(LegacyRefreshTokenAuthenticator::class)
        ->args([
            new Reference('gesdinet.jwtrefreshtoken.user_checker'),
            new Parameter('gesdinet_jwt_refresh_token.token_parameter_name'),
            new Reference('gesdinet.jwtrefreshtoken.request.extractor.chain'),
        ]);

    $services->set('gesdinet.jwtrefreshtoken.security.authentication.failure_handler')
        ->class(AuthenticationFailureHandler::class)
        ->args([
            new Reference('event_dispatcher'),
        ]);

    $services->set('gesdinet.jwtrefreshtoken.security.authentication.success_handler')
        ->class(AuthenticationSuccessHandler::class)
        ->args([
            new Reference('lexik_jwt_authentication.handler.authentication_success'),
            new Reference('event_dispatcher'),
        ]);

    $services->set('gesdinet.jwtrefreshtoken.security.refresh_token_authenticator')
        ->abstract()
        ->class(RefreshTokenAuthenticator::class)
        ->args([
            new Reference('gesdinet.jwtrefreshtoken.refresh_token_manager'),
            new Reference('event_dispatcher'),
            new Reference('gesdinet.jwtrefreshtoken.request.extractor.chain'),
            $abstractArg('user provider'),
            $abstractArg('authentication success handler'),
            $abstractArg('authentication failure handler'),
            $abstractArg('options'),
            new Reference('security.http_utils'),
        ]);

    $services->set(ClearInvalidRefreshTokensCommand::class)
        ->args([
            new Reference('gesdinet.jwtrefreshtoken.refresh_token_manager'),
        ])
        ->tag('console.command');

    $services->set(RevokeRefreshTokenCommand::class)
        ->args([
            new Reference('gesdinet.jwtrefreshtoken.refresh_token_manager'),
        ])
        ->tag('console.command');

    $services->set(LogoutEventListener::class)
        ->args([
            new Reference('gesdinet.jwtrefreshtoken.refresh_token_manager'),
            new Reference('gesdinet.jwtrefreshtoken.request.extractor.chain'),
            new Parameter('gesdinet_jwt_refresh_token.token_parameter_name'),
            new Parameter('gesdinet_jwt_refresh_token.cookie'),
            new Parameter('gesdinet_jwt_refresh_token.logout_firewall_context'),
        ])
        ->tag('kernel.event_listener', [
            'event' => 'Symfony\Component\Security\Http\Event\LogoutEvent',
            'method' => 'onLogout',
        ]);
};
