<?php

use Gesdinet\JWTRefreshTokenBundle\Command\ClearInvalidRefreshTokensCommand;
use Gesdinet\JWTRefreshTokenBundle\Command\RevokeRefreshTokenCommand;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\EventListener\AttachRefreshTokenOnSuccessListener;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Security\Authenticator\RefreshTokenAuthenticator;
use Gesdinet\JWTRefreshTokenBundle\Security\Provider\RefreshTokenProvider;
use Gesdinet\JWTRefreshTokenBundle\Service\RefreshToken;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerConfigurator $containerConfigurator) {
    $services = $containerConfigurator->services();

    $services->set('gesdinet.jwtrefreshtoken.send_token')
        ->class(AttachRefreshTokenOnSuccessListener::class)
        ->args([
            new Reference('gesdinet.jwtrefreshtoken.refresh_token_manager'),
            '%gesdinet_jwt_refresh_token.ttl%',
            new Reference('validator'),
            new Reference('request_stack'),
            '%gesdinet_jwt_refresh_token.user_identity_field%',
            '%gesdinet_jwt_refresh_token.token_parameter_name%',
            '%gesdinet_jwt_refresh_token.single_use%',
        ])
        ->tag('kernel.event_listener', [
            'event' => 'lexik_jwt_authentication.on_authentication_success',
            'method' => 'attachRefreshToken',
        ]);

    $services->set('gesdinet.jwtrefreshtoken.refresh_token_manager')
        ->class(RefreshTokenManager::class)
        ->public()
        ->args([
            new Reference('gesdinet.jwtrefreshtoken.object_manager'),
            '%gesdinet.jwtrefreshtoken.refresh_token.class%',
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
            '%gesdinet_jwt_refresh_token.ttl%',
            '%gesdinet_jwt_refresh_token.security.firewall%',
            '%gesdinet_jwt_refresh_token.ttl_update%',
            new Reference('event_dispatcher'),
        ]);

    $services->set('gesdinet.jwtrefreshtoken.user_provider')
        ->class(RefreshTokenProvider::class)
        ->args([
            new Reference('gesdinet.jwtrefreshtoken.refresh_token_manager'),
        ]);

    $services->set('gesdinet.jwtrefreshtoken.authenticator')
        ->class(RefreshTokenAuthenticator::class)
        ->args([
            new Reference('gesdinet.jwtrefreshtoken.user_checker'),
            '%gesdinet_jwt_refresh_token.token_parameter_name%',
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
