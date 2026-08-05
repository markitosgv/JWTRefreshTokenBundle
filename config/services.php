<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gesdinet\JWTRefreshTokenBundle\Command\ClearInvalidRefreshTokensCommand;
use Gesdinet\JWTRefreshTokenBundle\Command\RevokeRefreshTokenCommand;
use Gesdinet\JWTRefreshTokenBundle\EventListener\AttachRefreshTokenOnSuccessListener;
use Gesdinet\JWTRefreshTokenBundle\EventListener\LogoutEventListener;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGenerator;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\FamilyRefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\ListRefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RevokeRefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ChainExtractor;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\RequestBodyExtractor;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\RequestParameterExtractor;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\RequestCookieExtractor;
use Gesdinet\JWTRefreshTokenBundle\Security\Http\Authentication\AuthenticationFailureHandler;
use Gesdinet\JWTRefreshTokenBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Gesdinet\JWTRefreshTokenBundle\Security\Http\Authenticator\RefreshTokenAuthenticator;
use Gesdinet\JWTRefreshTokenBundle\Session\SessionLister;
use Lexik\Bundle\JWTAuthenticationBundle\Events;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $vendor = 'gesdinet_jwt_refresh_token';

    $refreshTokenTtl = param($vendor.'.ttl');
    $tokenParameterName = param($vendor.'.token_parameter_name');
    $singleUse = param($vendor.'.single_use');
    $singleUseTtlUpdate = param($vendor.'.single_use_ttl_update');
    $maxTokensPerUser = param($vendor.'.max_tokens_per_user');
    $cookie = param($vendor.'.cookie');
    $returnExpiration = param($vendor.'.return_expiration');
    $returnExpirationParameterName = param($vendor.'.return_expiration_parameter_name');
    $defaultInvalidBatchSize = param($vendor.'.default_invalid_batch_size');

    $services->set($vendor.'.event_listener.attach_refresh_token')
        ->class(AttachRefreshTokenOnSuccessListener::class)
        ->args([
            service($vendor.'.refresh_token_manager'),
            $refreshTokenTtl,
            service('request_stack'),
            $tokenParameterName,
            $singleUse,
            service($vendor.'.refresh_token_generator'),
            service($vendor.'.request.extractor.chain'),
            $cookie,
            $returnExpiration,
            $returnExpirationParameterName,
            $singleUseTtlUpdate,
            $maxTokensPerUser,
            service('security.token_storage')->nullOnInvalid(),
            // Only defined when reuse_detection is turned on, so this is null otherwise
            service($vendor.'.spent_refresh_token_registry')->nullOnInvalid(),
            param($vendor.'.max_session_lifetime'),
            // Only set once a firewall configures the authenticator, so it has to survive not being
            // there at all: the bundle works without one on any firewall
            param($vendor.'.firewall_options'),
            service('security.firewall.map')->nullOnInvalid(),
        ])
        ->tag('kernel.event_listener', [
            'event' => Events::AUTHENTICATION_SUCCESS,
            'method' => 'attachRefreshToken',
        ]);

    $services->set($vendor.'.refresh_token_generator')
        ->class(RefreshTokenGenerator::class)
        ->public()
        ->args([
            service($vendor.'.refresh_token_manager'),
        ]);

    $services->alias(RefreshTokenGeneratorInterface::class, $vendor.'.refresh_token_generator');

    // The manager itself comes from the backend in use, so only the aliases are shared. Every
    // Doctrine backend implements all four interfaces; the cache one takes back what it cannot
    // answer
    $services->alias(RefreshTokenManagerInterface::class, $vendor.'.refresh_token_manager');
    $services->alias(RevokeRefreshTokenManagerInterface::class, $vendor.'.refresh_token_manager');
    $services->alias(ListRefreshTokenManagerInterface::class, $vendor.'.refresh_token_manager');
    $services->alias(FamilyRefreshTokenManagerInterface::class, $vendor.'.refresh_token_manager');

    $services->set($vendor.'.session_lister')
        ->class(SessionLister::class)
        ->public()
        ->args([
            service($vendor.'.refresh_token_manager'),
        ]);

    $services->alias(SessionLister::class, $vendor.'.session_lister');

    $services->set($vendor.'.request.extractor.chain')
        ->class(ChainExtractor::class)
        ->public();

    $services->alias(ExtractorInterface::class, $vendor.'.request.extractor.chain');

    $services->set($vendor.'.request.extractor.request_body')
        ->class(RequestBodyExtractor::class)
        ->tag($vendor.'.request_extractor');

    $services->set($vendor.'.request.extractor.request_parameter')
        ->class(RequestParameterExtractor::class)
        ->tag($vendor.'.request_extractor');

    $services->set($vendor.'.request.extractor.request_cookie')
        ->class(RequestCookieExtractor::class)
        ->tag($vendor.'.request_extractor');

    $services->set($vendor.'.security.authentication.failure_handler')
        ->class(AuthenticationFailureHandler::class)
        ->args([
            service('event_dispatcher'),
        ]);

    $services->set($vendor.'.security.authentication.success_handler')
        ->class(AuthenticationSuccessHandler::class)
        ->args([
            service('lexik_jwt_authentication.handler.authentication_success'),
            service('event_dispatcher'),
        ]);

    $services->set($vendor.'.security.refresh_token_authenticator')
        ->abstract()
        ->class(RefreshTokenAuthenticator::class)
        ->args([
            service($vendor.'.refresh_token_manager'),
            service('event_dispatcher'),
            service($vendor.'.request.extractor.chain'),
            abstract_arg('user provider'),
            abstract_arg('authentication success handler'),
            abstract_arg('authentication failure handler'),
            abstract_arg('options'),
            service('security.http_utils'),
            service($vendor.'.refresh_token_reuse_detector')->nullOnInvalid(),
            service($vendor.'.refresh_rate_limiter')->nullOnInvalid(),
        ]);

    $services->set(ClearInvalidRefreshTokensCommand::class)
        ->args([
            service($vendor.'.refresh_token_manager'),
            $defaultInvalidBatchSize,
        ])
        ->tag('console.command');

    $services->set(RevokeRefreshTokenCommand::class)
        ->args([
            service($vendor.'.refresh_token_manager'),
        ])
        ->tag('console.command');

    $services->set($vendor.'.security.listener.logout')
        ->abstract()
        ->class(LogoutEventListener::class)
        ->args([
            service($vendor.'.refresh_token_manager'),
            service($vendor.'.request.extractor.chain'),
            $tokenParameterName,
            $cookie,
        ]);
};
