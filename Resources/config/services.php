<?php

use Gesdinet\JWTRefreshTokenBundle\Command\ClearInvalidRefreshTokensCommand;
use Gesdinet\JWTRefreshTokenBundle\Command\RevokeRefreshTokenCommand;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\EventListener\AttachRefreshTokenOnSuccessListener;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGenerator;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Security\Http\Authentication\AuthenticationFailureHandler;
use Gesdinet\JWTRefreshTokenBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Gesdinet\JWTRefreshTokenBundle\Security\Authenticator\RefreshTokenAuthenticator as LegacyRefreshTokenAuthenticator;
use Gesdinet\JWTRefreshTokenBundle\Security\Http\Authenticator\RefreshTokenAuthenticator;
use Gesdinet\JWTRefreshTokenBundle\Security\Provider\RefreshTokenProvider;
use Gesdinet\JWTRefreshTokenBundle\Service\RefreshToken;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerConfigurator $container) {
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

    $services->set('gesdinet.jwtrefreshtoken')
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
        ->class(RefreshTokenProvider::class)
        ->args([
            new Reference('gesdinet.jwtrefreshtoken.refresh_token_manager'),
        ]);

    $services->set('gesdinet.jwtrefreshtoken.authenticator')
        ->class(LegacyRefreshTokenAuthenticator::class)
        ->args([
            new Reference('gesdinet.jwtrefreshtoken.user_checker'),
            new Parameter('gesdinet_jwt_refresh_token.token_parameter_name'),
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
            // User provider parameter is added in the security factory, change to an abstract argument reference when Symfony 5.1 and newer are required
            // Success handler parameter is added in the security factory, change to an abstract argument reference when Symfony 5.1 and newer are required
            // Failure handler parameter is added in the security factory, change to an abstract argument reference when Symfony 5.1 and newer are required
            // Options parameter is added in the security factory, change to an abstract argument reference when Symfony 5.1 and newer are required
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
};
