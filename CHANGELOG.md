# Changelog

## Unreleased

### Changed

* The refresh token is read from a JSON body whatever the request declares its content type to be. A client that sets no header, which is what `fetch()` does when given none, or a proxy that strips it, was answered as though no token had been supplied
* `delete()` reports what the storage actually deleted rather than one row after reading the token back. Two callers racing for the same token were both told they had deleted it, which is the answer a single use token needs to tell them apart
* Logging out invalidates the refresh token of the user logging out and not one belonging to somebody else, which is answered as a token that no longer exists. A request with no authenticated user still invalidates the token it carries
* `gesdinet:jwt:clear` reports how many tokens it revoked and lists them only with `-v`. A run clearing a backlog revokes thousands, and listing them all buried the count

### Added

* A DBAL backend, configured with `dbal_connection`, storing the tokens through a plain connection rather than the ORM or the ODM. The table and its columns are named with `dbal_table_name` and `dbal_columns`, and `dbal_auto_create_table` creates the table on the first request when a migration is not practical
* `RevokeRefreshTokenManagerInterface`, aliased to the manager so it can be injected by type, whose `revokeAllForUser()` revokes every refresh token issued to a user, for a password reset or an account being disabled, and returns how many were revoked. It is deleted by the database, so no token is hydrated and no life-cycle event is raised
* `DeleteRefreshTokenRepositoryInterface::deleteByUser()` backs it. Both are separate interfaces, so an existing manager or repository keeps working without them

## 2.1.0

### Fixed

* `revokeAllInvalidBatch()` returned the last batch read, which is empty once the loop ends, so it always returned an empty array and `gesdinet:jwt:clear` reported that there was nothing to revoke after deleting tokens
* `revokeAllInvalidBatch()` looped forever with the MongoDB ODM, as its condition tested the repository result with `empty()`, which is never true for the iterator the ODM returns
* `revokeAllInvalidBatch()` skipped expired tokens: each batch is deleted before the next is read, so the remaining tokens shift down and the offset has to stay where it is
* The document repository reads its results through `Query::getIterator()`, so they are the iterable the interface promises
* `delete()` returns `0` when the token is not in storage, which the ODM reported as `1` regardless
* The success listener no longer brings the request down when a token has no expiration date, and both of its checks for a usable token string now agree
* The logout listener clears the cookie on the response it just built rather than reading it back from the event
* A refresh token without a username is rejected with an `InvalidTokenException` instead of a `TypeError` while building the passport
* `refresh_token_class` reports a configuration error when the class cannot be loaded, instead of a `TypeError` while building the container
* `gesdinet:jwt:clear` rejects a `--batch-size` that is not a positive number, which read no tokens and reported success while leaving every expired token in place
* Both request extractors check what they read before returning it, and `PostRefreshTokenAuthenticationToken` checks the serialized state it is given

### Changed

* `AuthenticationSuccessHandler::onAuthenticationSuccess()` is typed `?Response`, matching the handler it decorates. What is returned at runtime has not changed
* `RefreshTokenRepositoryInterface` documents, through a `@method` tag, that `findOneBy()` takes an optional `$orderBy` argument
* The `php` constraint is written as `^8.2`, the same minimum without claiming support for a future PHP 9
* Dropped the compatibility shims for Symfony versions below 6.4, which is already the minimum

See [UPGRADE-2.1.md](UPGRADE-2.1.md) for the details.

## 2.0.0

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
