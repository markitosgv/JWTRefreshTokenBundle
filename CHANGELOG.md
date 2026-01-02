# Changelog

## Unreleased

* [B/C Break] Removed the `Gesdinet\JWTRefreshTokenBundle\EventListener\LogoutEventListener` service definition; if needed, an abstract `gesdinet_jwt_refresh_token.security.listener.logout` definition replaces it and does not have a `kernel.event_listener` tag
* [B/C Break] The `logout_firewall` config node default value is now null
* Deprecated the `logout_firewall` config node, the `invalidate_token_on_logout` option should be set on the `refresh_jwt` authenticator
* Added support for `doctrine/persistence` 4.0

## 1.4.0

* Dropped support for Symfony 4.4

## 1.3.0

* Added support for partitioned cookies

## 1.2.0

* Added support for `LexikJWTAuthenticationBundle` 3.0
* Added support for Symfony 7.0

## 1.1.0

* [B/C Break] Changed the object mappings to mapped superclasses, this requires updating your app's configuration
* Added support for checking the request path in the `refresh_jwt` authenticator
* Deprecated not configuring the request path to check in the `refresh_jwt` authenticator
* Added feature to add the expiration timestamp on the response

## 1.0.0

* Dropped support for MongoDB ODM 1.x
* Dropped support for Symfony 3.4
* Added support for Symfony 6.0
* Added a LogoutEventListener that will invalidate the supplied refresh token and clear the cookie (if configured) when a LogoutEvent is triggered on the configured firewall.

## 1.0.0-beta4

* Added `Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenRepositoryInterface`
* `Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenManager` now requires all object repositories implement `Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenRepositoryInterface`

## 1.0.0-beta2

* Added `Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface` as an interface for extracting the refresh token from the request, implementations provided by this bundle include:
  * `Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ChainExtractor` - Calls all registered extractors to find the request token (by default, this extractor is aliased to the interface in the DI container)
  * `Gesdinet\JWTRefreshTokenBundle\Request\Extractor\RequestBodyExtractor` - Decodes a JSON request body and loads the token from it
  * `Gesdinet\JWTRefreshTokenBundle\Request\Extractor\RequestParameterExtractor` - Loads the refresh token by calling `$request->get()`
* Removed the `Gesdinet\JWTRefreshTokenBundle\Request\RequestRefreshToken` class, a `Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface` implementation should be used instead
* `Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface` now extends `Stringable`, refresh token models now require a `__toString()` method
