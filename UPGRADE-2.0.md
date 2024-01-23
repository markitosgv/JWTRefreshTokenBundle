# Upgrade from 1.x to 2.0

The below guide will assist in upgrading from the 1.x versions to 2.0.

## Bundle Requirements

- Symfony 5.4, 6.4, or 7.0+
- PHP 8.1 or later

## General changes

- The `refresh_token_class` config node is now required and validates that the class implements `Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface`
- The `Gesdinet\JWTRefreshTokenBundle\Security\Http\Authenticator\RefreshTokenAuthenticator::supports()` method now only checks if the request path matches the `check_path` configuration for the authenticator
- Standardized all container IDs to use the `gesdinet_jwt_refresh_token` prefix
- Made several classes final
- Added parameter and return typehints

## Removed Features

- Removed classes supporting authentication for Symfony 5.3 and earlier
- Removed the `AbstractRefreshToken` classes from the `Gesdinet\JWTRefreshTokenBundle\Document` and `Gesdinet\JWTRefreshTokenBundle\Entity` namespaces, use the `RefreshToken` class from the same namespace instead
- Removed `Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface::create()` and its implementations, a `Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface` implementation should be used instead
- Removed `Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManager`, implement `Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface` directly instead
- Removed automatic token generation from `Gesdinet\JWTRefreshTokenBundle\Model\AbstractRefreshToken::setRefreshToken()`, a token is now required
- Removed `Gesdinet\JWTRefreshTokenBundle\DependencyInjection\Compiler\ObjectManagerCompilerPass` and inlined its logic to the container extension
- Removed the `gesdinet.jwtrefreshtoken.object_manager.id` container parameter
- Removed deprecated configuration nodes:
    - `firewall` - No replacement
    - `user_provider` - No direct replacement, the user provider should be set on the security firewall configuration instead
    - `user_identity_field` - No replacement
    - `user_checker` - No direct replacement, the user checker should be set on the security firewall configuration instead
    - `refresh_token_entity` - Use the `refresh_token_class` node instead
    - `entity_manager` - Use the `object_manager` node instead
    - `doctrine_mappings` - No replacement
    - `manager_type` - Set the `object_manager` when needed
    - `logout_firewall` - Set the `invalidate_token_on_logout` config on the `refresh_jwt` authenticator instead
