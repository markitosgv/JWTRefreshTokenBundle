# Changelog

## 3.0.0

See [UPGRADE-3.0.md](UPGRADE-3.0.md) for what to check before upgrading, and
[UPGRADE-RECTOR.md](UPGRADE-RECTOR.md) if you are coming from further back than 2.2.

**This release needs a schema change before the application will run.** Refresh tokens gained
`family` and `family_valid` columns, and Doctrine reads every mapped field.

### Added

* Refresh tokens belong to a family: a token issued in place of another carries the family of the one it replaced, so a login and every refresh descending from it share one value. That is what makes a session addressable — without it, "end this session" can only mean "delete this one token", which the next refresh has already replaced. Kept in `Model\FamilyAwareRefreshTokenInterface` and `Model\RefreshTokenFamilyTrait`, separate from `RefreshTokenInterface` so a token class of your own is untouched. `Model\FamilyRefreshTokenManagerInterface::revokeFamily()` revokes a whole chain
* `reuse_detection`, which recognises a single use refresh token being presented after it was spent and revokes the chain it belonged to. Rotation alone leaves a stolen token working until the legitimate client happens to refresh, and nobody learns why it broke; a spent token is deleted, so a replay is indistinguishable from any other unknown token unless spent ones are remembered. Off by default, refused without `single_use`, and it dispatches `RefreshTokenReuseDetectedEvent` because the bundle cannot tell theft from a client racing itself
* `max_session_lifetime`, a ceiling on how long a chain of refreshes may go on for, whatever `ttl` says. A ttl that starts over on every rotation means a session never ends. The deadline is set when a chain starts and carried along it unchanged
* `block_jwts_on_revocation`, which refuses the JWTs already issued to a user when `revokeAllForUser()` takes their refresh tokens away. Lexik's blocklist cannot do this — it is keyed by `jti`, so it withdraws a token you are holding, and these are in clients — so what is recorded is when the revocation happened, per user, and any JWT issued at or before it is refused on decode
* `rate_limiter`, bounding how often the refresh endpoint will answer. Consumed before the token is looked at, so a refusal costs no query and its timing says nothing about whether the token exists. Refused requests answer `429` with `Retry-After`. Keyed by IP or by token, which is a trade-off rather than a detail. Needs `symfony/rate-limiter`
* `Session\SessionLister`, for showing a user where they are signed in and letting them end one. Grouping by chain is what turns `findAllForUser()` from a list of moments into a list of sessions. `end()` checks the chain belongs to the caller, since a session list is exactly where such an identifier gets handed out
* `cache_pool`, storing the tokens in a PSR-6 pool instead of a database. Expiry is then the pool's job, so nothing has to be scheduled to clear them. It implements only what a pool can honour, and `max_tokens_per_user` and `reuse_detection` are configuration errors alongside it rather than options that quietly do nothing
* The refresh behaviour can be configured per firewall: `ttl`, `ttl_update`, `token_parameter_name`, `single_use`, `single_use_ttl_update`, `max_session_lifetime`, `max_tokens_per_user`, `return_expiration` and `return_expiration_parameter_name` on the `refresh_jwt` authenticator. Every one defaults to null, meaning "whatever the bundle says", which is not the same as defaulting to its current value. Cookie settings stay global
* `block_previous_jwt`, which blocks the JWT a refresh replaces through LexikJWTAuthenticationBundle 3's blocklist, so refreshing no longer leaves the previous JWT usable for the rest of its lifetime. A request carrying no JWT, and a JWT that no longer parses, are left alone: an expired one is refused everywhere already. Off by default, and reported at compile time when Lexik's `blocklist_token` is not on
* Rector rule sets for every hop from 1.5 to 3.0, under `rector/sets`, with the upgrade path in [UPGRADE-RECTOR.md](UPGRADE-RECTOR.md). Only the 1.5 to 2.0 set rewrites anything; the other three are empty and say why

### Changed

* **BC break**: PHP 8.4 or later, Symfony 8.0 or later, and LexikJWTAuthenticationBundle 3. Symfony 6.4 and the 7 branch are dropped, which takes PHP 8.2 and 8.3 with them since Symfony 8 needs 8.4
* **BC break**: `check_path` is required on the `refresh_jwt` authenticator. It defaulted to `/login_check`, Lexik's login path, which is never right for a refresh endpoint: left alone the authenticator took no requests and the router reported the refresh route as having no controller
* **BC break**: `RefreshEvent` takes the request the refresh was made with, and `$firewallName` loses its default. Listeners gain `getRequest()`; only code constructing the event is affected
* **BC break**: doctrine/dbal 3 is dropped, along with the shims for `quoteIdentifier()` and `setPrimaryKey()`
* `dbal_columns`, when configured, has to name the `id` column. A map without one produced a table whose expired tokens could never be revoked: batches are deleted by identifier, so with none to delete by, `gesdinet:jwt:clear` read the same batch forever
* **BC break**: the exceptions, the bundle class, the failure response and the post-refresh security token are `final`. The token models, `AbstractRefreshToken` and the two repositories are deliberately left extendable, being the documented way to bring your own
* Every file declares `strict_types`, so the calls this bundle makes pass their arguments without coercion
* The codebase uses the PHP 8.4 syntax its minimum already requires, and `rector.php` and `.php-cs-fixer.php` now keep it that way. `rector/rector` had been a development dependency for a long time with nothing configured to run it

### Fixed

* DBAL index names include the table name. `UNIQ_REFRESH_TOKEN`, `IDX_USERNAME` and `IDX_VALID` were fixed whatever the table was called, and index names are scoped to the schema on PostgreSQL and to the whole database on SQLite — so a second table managed by the bundle could not be created, and the error named an index rather than anything identifying this bundle. Existing tables are untouched, since the schema is only built when absent
* `Session\SessionLister` keys chains by `array-key` rather than `string`. A family is 32 hex characters, and PHP turns one that happens to be all digits into an integer key
* The nineteen open code scanning alerts. Seven were real, including a missing mutation annotation on `RefreshTokenFamilyTrait` that stopped psalm's taint analysis reasoning about where a family came from, and four array shapes that were sealed promises about keys the method never looks at. The rest are by design or belong to Symfony, Doctrine and API Platform, and are suppressed in `psalm.xml.dist` scoped to the files they concern, each with the reason

## 2.2.1

### Fixed

* `ttl` and `max_tokens_per_user` can be read from an environment variable again. Both were checked with a `validate()` closure, which rejects every `%env(int:...)%` put in front of them: the container is compiled a second time with a sample value of the declared type in place, and for an integer that sample is `0`. `min()` is skipped while a placeholder is being handled and a closure is not, so the built-in constraint is used instead. Reported as #431 against 2.2.0, where the `ttl` check was introduced; 2.1.0 has no such check and is unaffected

## 2.2.0

Released 2026-08-04. See [UPGRADE-2.2.md](UPGRADE-2.2.md) for what to check before upgrading.

### Changed

* An expired JWT can be exchanged with `jwt` and `refresh_jwt` on the same firewall. Symfony orders authenticators by the priority their factories declare rather than by the order written on the firewall, and this one sat below the JWT authenticator, which rejected the expired token before the refresh authenticator was reached. Reordering them in `security.yaml` never had any effect. It now sits above it, and since it only takes over requests matching its `check_path`, nothing else changes
* A refresh makes one query for the token rather than two. The authenticator loads it to authenticate with, and the listener loaded it again; Symfony puts the authenticated token in storage before calling the success handler, so it is already to hand. The value is compared rather than trusted on type alone, so a token left in storage by anything else is never acted on — which also means hashed storage, where the comparison cannot match, queries as it did before
* `object_manager` naming a service that does not exist is reported as such, with the object managers there are, rather than as Symfony being unable to replace an alias with a definition. The usual cause is giving the name an entity manager is configured under instead of its service id, which the message now says
* The refresh token is read from a JSON body whatever the request declares its content type to be. A client that sets no header, which is what `fetch()` does when given none, or a proxy that strips it, was answered as though no token had been supplied
* `delete()` reports what the storage actually deleted rather than one row after reading the token back. Two callers racing for the same token were both told they had deleted it, which is the answer a single use token needs to tell them apart
* Logging out invalidates the refresh token of the user logging out and not one belonging to somebody else, which is answered as a token that no longer exists. A request with no authenticated user still invalidates the token it carries
* `gesdinet:jwt:clear` reports how many tokens it revoked and lists them only with `-v`. A run clearing a backlog revokes thousands, and listing them all buried the count
* A `ttl` of `0` or less is rejected. It describes a token that has expired by the time it is handed over, so every refresh made with one fails, and it is what an application reaching for a token that never expires tends to try first
* `cookie.same_site` can be read from an environment variable. It accepted a fixed list of words, and an environment variable is checked at compile time against an empty sample value of its type, so every variable put in front of it was rejected whatever it held. It now accepts what `Symfony\Component\HttpFoundation\Cookie` itself documents: the three values in any case, or an empty one to leave the attribute off the cookie
* The refresh token cookie expires when the token inside it does rather than a `ttl` from when it was set. The two only ever agreed because the token was issued with a full `ttl`, which `single_use_ttl_update` no longer guarantees
* The manager service is defined once by the backend in use. `config/services.php` also defined it, naming a class it never imported and an object manager the DBAL backend does not have, which went unnoticed only because both backends overwrote it

### Added

* `single_use_ttl_update`, on by default, which keeps a token issued in place of a single use one starting its ttl over. Turned off, the replacement expires when the one it replaced would have, so refreshing cannot be chained indefinitely and the user signs in again a `ttl` after the first token was issued
* A DBAL backend, configured with `dbal_connection`, storing the tokens through a plain connection rather than the ORM or the ODM. The table and its columns are named with `dbal_table_name` and `dbal_columns`, and `dbal_auto_create_table` creates the table on the first request when a migration is not practical
* `RevokeRefreshTokenManagerInterface`, aliased to the manager so it can be injected by type, whose `revokeAllForUser()` revokes every refresh token issued to a user, for a password reset or an account being disabled, and returns how many were revoked. It is deleted by the database, so no token is hydrated and no life-cycle event is raised
* `DeleteRefreshTokenRepositoryInterface::deleteByUser()` backs it. Both are separate interfaces, so an existing manager or repository keeps working without them
* `ListRefreshTokenManagerInterface::findAllForUser()`, which returns every refresh token issued to a user, the one expiring last first, for showing somebody the sessions they have open. Expired ones are included, since they are still rows, and `isValid()` tells them apart
* `RevokeRefreshTokenManagerInterface` and `ListRefreshTokenManagerInterface` are aliased to the manager for every backend, the DBAL one included. Revoking by user was only offered to the ORM and the ODM
* `hash_tokens`, which stores `sha256$` and the hash of a refresh token rather than the token, so a copy of the database cannot be used to refresh. Off by default. Turning it on signs nobody out: tokens already stored are taken as they are and rewritten hashed the first time they are used, until `accept_stored_in_the_clear` is turned off. Note that `getRefreshToken()` then returns the stored hash, since that is what is stored
* `max_tokens_per_user`, a limit on how many refresh tokens a user may hold at once, which is a limit on signed-in devices since each login stores one. Signing in beyond it revokes the session that has gone longest without being refreshed, expired tokens first. Unlimited when not set
* `RevokeRefreshTokenManagerInterface::revokeAllButNewestForUser()` and `DeleteRefreshTokenRepositoryInterface::deleteAllButNewestForUser()` back it
* `api_platform.enabled`, which documents the refresh token in the OpenAPI specification API Platform generates: the `refresh_token` Lexik's login schema was missing, and the refresh endpoint nobody documented, one path per firewall the authenticator is on. It follows the bundle's own configuration, so the cookie replacing the body is documented as such rather than promising a field that never arrives. Off by default, since an application documenting it by hand would end up with it twice
* `refresh_token_manager`, naming a service of your own, which replaces the manager the bundle would build and wires none of its storage, so the tokens can live in a PDO repository or anywhere else and Doctrine need not be installed at all. `RefreshTokenManagerInterface` is now held to the same test suite from outside the bundle, so it stays implementable

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
