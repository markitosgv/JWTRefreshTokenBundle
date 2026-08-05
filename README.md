# JWTRefreshTokenBundle

[![Run Tests](https://github.com/markitosgv/JWTRefreshTokenBundle/actions/workflows/run-tests.yml/badge.svg?branch=master)](https://github.com/markitosgv/JWTRefreshTokenBundle/actions/workflows/run-tests.yml)
[![PHP](https://img.shields.io/packagist/dependency-v/gesdinet/jwt-refresh-token-bundle/php?label=PHP&color=777BB4&logo=php&logoColor=white)](https://php.net)
[![Symfony](https://img.shields.io/packagist/dependency-v/gesdinet/jwt-refresh-token-bundle/symfony%2Fsecurity-bundle?label=Symfony&color=000000&logo=symfony&logoColor=white)](https://symfony.com)
[![Latest Stable Version](https://poser.pugx.org/gesdinet/jwt-refresh-token-bundle/v/stable)](https://packagist.org/packages/gesdinet/jwt-refresh-token-bundle)
[![Total Downloads](https://poser.pugx.org/gesdinet/jwt-refresh-token-bundle/downloads)](https://packagist.org/packages/gesdinet/jwt-refresh-token-bundle)
[![Monthly Downloads](https://poser.pugx.org/gesdinet/jwt-refresh-token-bundle/d/monthly)](https://packagist.org/packages/gesdinet/jwt-refresh-token-bundle/stats)
[![Daily Downloads](https://poser.pugx.org/gesdinet/jwt-refresh-token-bundle/d/daily)](https://packagist.org/packages/gesdinet/jwt-refresh-token-bundle/stats)
[![License](https://poser.pugx.org/gesdinet/jwt-refresh-token-bundle/license)](https://packagist.org/packages/gesdinet/jwt-refresh-token-bundle)
[![Coverage Status](https://coveralls.io/repos/github/markitosgv/JWTRefreshTokenBundle/badge.svg?branch=master)](https://coveralls.io/github/markitosgv/JWTRefreshTokenBundle?branch=master)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%2010-brightgreen.svg)](https://phpstan.org/)
[![Rector](https://img.shields.io/badge/Rector-enabled-brightgreen.svg)](https://getrector.org/)

Packagist has no badge for the downloads of a single release, but it does chart them: see the [download statistics](https://packagist.org/packages/gesdinet/jwt-refresh-token-bundle/stats) for the breakdown per version.

The purpose of this bundle is manage refresh tokens with JWT (Json Web Tokens) in an easy way. This bundles uses [LexikJWTAuthenticationBundle](https://github.com/lexik/LexikJWTAuthenticationBundle). Supports Doctrine ORM/ODM.

## Prerequisites

This bundle requires PHP 8.2 or later and Symfony 6.4, 7.2+ or 8.0+.
For support with older Symfony versions, please use the 1.x release.
**Protip:** Though the bundle doesn't force you to do so, it is highly recommended to use HTTPS.

When upgrading, see [UPGRADE-3.0.md](UPGRADE-3.0.md), [UPGRADE-2.2.md](UPGRADE-2.2.md), [UPGRADE-2.1.md](UPGRADE-2.1.md) and [UPGRADE-2.0.md](UPGRADE-2.0.md) for what
changed, and the [changelog](CHANGELOG.md) for the full list.

Coming from an older version, [UPGRADE-RECTOR.md](UPGRADE-RECTOR.md) walks the whole path from 1.5
one release at a time, with rule sets for the renames Rector can do and a checklist for the parts it
cannot.

## Installation

### Step 1: Download the Bundle

**You must also install either the Doctrine ORM or MongoDB ODM, these packages are not installed automatically with this bundle. Failing to do so may trigger errors on installation.**
With Doctrine's ORM

```bash
composer require doctrine/orm doctrine/doctrine-bundle gesdinet/jwt-refresh-token-bundle
```

With Doctrine's MongoDB ODM

```bash
composer require doctrine/mongodb-odm doctrine/mongodb-odm-bundle gesdinet/jwt-refresh-token-bundle
```

Alternatively, a custom persistence layer can be used.
For that purpose, you must:

* provide an implementation of `Doctrine\Persistence\ObjectManager`
* configure the bundle to [use your object manager](#use-another-object-manager)

### Step 2: Enable the Bundle

#### For Symfony Flex Applications

For an application using Symfony Flex the bundle should be automatically registered, but if not you will need to add it to your `config/bundles.php` file.

```php
<?php
return [
    //...
    Gesdinet\JWTRefreshTokenBundle\GesdinetJWTRefreshTokenBundle::class => ['all' => true],
];
```

### Step 3: Configure the Bundle

#### Symfony Flex Application

For an application using Symfony Flex, a recipe should have been applied to your application. If not, you will need to make the following changes:

1. Configure the refresh token class. Create the `config/packages/gesdinet_jwt_refresh_token.yaml` file with the below contents:

```yaml
gesdinet_jwt_refresh_token:
    refresh_token_class: App\Entity\RefreshToken # This is the class name of the refresh token, you will need to adjust this to match the class your application will use
```

2. Create the object class.
If you are using the Doctrine ORM, the below contents should be placed at `src/Entity/RefreshToken.php`:

```php
<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;
#[ORM\Entity]
#[ORM\Table(name: 'refresh_tokens')]
class RefreshToken extends BaseRefreshToken
{
}
```

If you are using the Doctrine MongoDB ODM, the below contents should be placed at `src/Document/RefreshToken.php` (remember to update the `refresh_token_class` configuration above to match):

```php
<?php
namespace App\Document;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Gesdinet\JWTRefreshTokenBundle\Document\RefreshToken as BaseRefreshToken;
#[ODM\Document(collection: 'refresh_tokens')]
class RefreshToken extends BaseRefreshToken
{
}
```

##### If your application sets `auto_mapping: false`

The class above inherits its mapping — the identifier, the columns, the table name — from the base
class, whose mapping ships with the bundle. Doctrine registers it for you only while
`doctrine.orm.auto_mapping` is `true`, which it is by default. With it turned off, the base class is
never mapped and the class above inherits nothing, which Doctrine reports as:

```text
No identifier/primary key specified for Entity "App\Entity\RefreshToken"
sub class of "Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken".
Every Entity must have an identifier/primary key.
```

or, depending on how far Doctrine got before it noticed:

```text
Class "App\Entity\RefreshToken" sub class of
"Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken"
is not a valid entity or mapped super class.
```

Name the mapping alongside your own and it is registered again:

```yaml
# config/packages/doctrine.yaml
doctrine:
    orm:
        auto_mapping: false
        mappings:
            App:
                # ... your own mapping
            GesdinetJWTRefreshToken:
                type: xml
                is_bundle: false
                dir: '%kernel.project_dir%/vendor/gesdinet/jwt-refresh-token-bundle/config/doctrine'
                prefix: 'Gesdinet\JWTRefreshTokenBundle\Entity'
```

The base class is a mapped superclass, so this adds no table of its own.

The same applies with more than one entity manager, for a different reason: Doctrine allows
`auto_mapping` on only one of them, so the bundle's mapping lands there and nowhere else. Naming the
manager in `object_manager` points the bundle at it, and the mapping has to be declared under that
manager as well, or `doctrine:schema:update --em=...` finds nothing to create:

```yaml
gesdinet_jwt_refresh_token:
    # A service id, not the name: an entity manager named "custom" is
    # "doctrine.orm.custom_entity_manager"
    object_manager: doctrine.orm.custom_entity_manager

doctrine:
    orm:
        entity_managers:
            custom:
                connection: custom_connection
                mappings:
                    GesdinetJWTRefreshToken:
                        type: xml
                        is_bundle: false
                        dir: '%kernel.project_dir%/vendor/gesdinet/jwt-refresh-token-bundle/config/doctrine'
                        prefix: 'Gesdinet\JWTRefreshTokenBundle\Entity'
```

Your own token class needs mapping under that manager too, since it is the one that gets a table.

The other way out is to leave the bundle's mapping alone and
[map the entity yourself](#mapping-the-entity-yourself), which is also what you want if you need to
choose the identifier strategy.

### Step 4

#### Define the refresh token route

Open your routing configuration file and add the following route to it:

```yaml
# config/routes.yaml
api_refresh_token:
    path: /api/token/refresh
# ...
```

The route has no controller on purpose. It exists so that the path is routable, and the firewall
authenticator configured below answers it before any controller would be reached. Giving it
`controller: gesdinet.jwtrefreshtoken::refresh`, as versions before 2.0 did, loads a class that no
longer exists.

Which means the two have to agree. `check_path` on the authenticator is what decides the requests it
takes over, and it defaults to `/login_check`, so `refresh_jwt: ~` leaves the refresh route
unanswered. The request then reaches the router, which finds the route and no controller behind it:

```text
Unable to find the controller for path "/auth-token/refresh". The route is wrongly configured.
```

The message points at the route, but the route is fine — it is the authenticator that never took the
request. Setting `check_path` to the same path, or to the route name, is the fix.

#### Configure the authenticator

To enable the authenticator, you should add it to your API firewall(s) alongside the `json_login` and `jwt` authenticators.
The complete firewall configuration should look similar to the following:

```yaml
# config/packages/security.yaml
security:
    firewalls:
        api:
            pattern: ^/api
            stateless: true
            entry_point: jwt
            json_login:
                check_path: /api/login # or, if you have defined a route for your login path, the route name you used
                success_handler: lexik_jwt_authentication.handler.authentication_success
                failure_handler: lexik_jwt_authentication.handler.authentication_failure
            jwt: ~
            refresh_jwt:
                check_path: /api/token/refresh # or, you may use the `api_refresh_token` route name
                # or if you have more than one user provider
                # provider: user_provider_name
    # ...
    access_control:
        # ...
        - { path: ^/api/(login|token/refresh), roles: PUBLIC_ACCESS }
        # ...
# ...
```

`jwt` and `refresh_jwt` sit on the same firewall, and the refresh authenticator is reached first
whatever order they appear in here: Symfony orders authenticators by a priority the bundles declare,
not by the file. That ordering is what lets an expired JWT be exchanged at all — reached second, the
JWT authenticator would reject the request before the refresh one saw it. It only takes over
requests matching its `check_path`, so everything else still authenticates with the JWT as usual.

### Step 5: Update your database schema

You will need to add the table for the refresh tokens to your application's database.

With migrations:

```bash
# If using the MakerBundle:
php bin/console make:migration
# Without the MakerBundle:
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

**Read the generated migration before running it.** It is not generated from this bundle. Doctrine
compares your mapping against the whole database, so the migration covers every table it finds, not
only this one. In a project where Doctrine does not map everything — tables kept by hand, by another
tool, or shared with another application — the migration drops every table it does not know about.

Telling Doctrine which tables are its own leaves the rest alone:

```yaml
# config/packages/doctrine.yaml
doctrine:
    dbal:
        # Only tables matching this are compared. The form to exclude what Doctrine does not manage:
        schema_filter: '~^(?!legacy_)~'
        # Or, where this bundle's table is the only mapped one, name it and nothing else:
        # schema_filter: '~^refresh_tokens$~'
```

Without migrations (**NOT RECOMMENDED**):

```bash
php bin/console doctrine:schema:update --dump-sql
php bin/console doctrine:schema:update --force
```

This makes the same comparison, without writing a file to read first, so with no `schema_filter` in
place it drops those tables there and then. `--dump-sql` prints what it would run.

## Usage

The below options can be configured through the bundle's configuration in the `config/packages/gesdinet_jwt_refresh_token.yaml` file (make sure to create it if it does not already exist).

### Token TTL

You can define the refresh token TTL, this value is set in seconds and defaults to 1 month. You can change this value adding this line to your config:

```yaml
gesdinet_jwt_refresh_token:
    ttl: 2592000
```

There is no value meaning never. A token is valid until this many seconds after it was issued, so a
token meant to outlive the application is a large number of seconds rather than a special one:

```yaml
gesdinet_jwt_refresh_token:
    ttl: 315360000 # ten years
```

`0` is not it, and neither is a negative number: those describe a token that has already expired
when it is handed over, so every refresh made with it fails. They are rejected rather than accepted
quietly.

Before reaching for ten years, it is worth being clear about what that is. The refresh token is the
credential that gets a user back in without their password, so one that never expires is a password
that cannot be changed and that you have stored in your database. It cannot be cleaned up either,
since `gesdinet:jwt:clear` finds tokens by their expiry, so the table only grows.

A long session without a permanent credential is what [single use tokens](#single-use-tokens) are
for: each refresh replaces the token, so a stolen one stops working as soon as the real user
refreshes, and `ttl` is how long a user may be away before signing in again rather than how long the
credential lives.

### Update Token TTL

You can configure the bundle to refresh the TTL on a refresh token when it is used, by default this feature is disabled. You can change this value adding this line to your config:

```yaml
gesdinet_jwt_refresh_token:
    ttl_update: true
```

### Refresh Token Parameter Name

The refresh token is called `refresh_token` by default. One setting changes it:

```yaml
gesdinet_jwt_refresh_token:
    token_parameter_name: refreshToken
```

It is the name everywhere the token appears, not only where it is read: the JSON body and the query
string it is read from, the cookie it is read from and set in, the key it is returned under in the
response body, and the cookie cleared on logout. There is no separate setting for the cookie name.

So changing it changes both ends at once. A client reading `refresh_token` out of the response, or
sending it back under that name, has to be updated with it.

### Return Expiration Timestamp

If set to true, the expiration Unix timestamp will be added to the response.

```yaml
gesdinet_jwt_refresh_token:
    return_expiration: true
```

The default parameter name is `refresh_token_expiration`. You can change the parameter name by adding this line to your config and changing it:

```yaml
gesdinet_jwt_refresh_token:
    return_expiration_parameter_name: refresh_token_expiration
```

This works alongside the cookie below. With the token moved into an `HttpOnly` cookie and taken out
of the body, the expiration stays in the body, so a frontend that cannot read the token still knows
how long the refresh session lasts:

```yaml
gesdinet_jwt_refresh_token:
    return_expiration: true
    cookie:
        enabled: true
        http_only: true
        remove_token_from_body: true
```

The response then carries the expiration and no token, while the token travels in a cookie the
browser sends on its own:

```json
{
    "token": "...",
    "refresh_token_expiration": 1793923200
}
```

### Set The User Provider

You can define a user provider to use for the authenticator its configuration.
Note, if your application has multiple user providers, you **MUST** configure this value for either the firewall or the provider.

```yaml
# config/packages/security.yaml
security:
    firewalls:
        api:
            pattern: ^/api
            stateless: true
            entry_point: jwt
            json_login: ~
            jwt: ~
            refresh_jwt:
                check_path: /api/token/refresh
                provider: user_provider_service_id
```

By default, when a user provider is not specified, then the user provider for the firewall is used instead.

### Set The User Checker

You can define a user checker to use for the firewall as part of the firewall configuration:

```yaml
# config/packages/security.yaml
security:
    firewalls:
        api_token_refresh:
            pattern: ^/api/token/refresh
            stateless: true
            user_checker: user_checker_service_id
            refresh_jwt: ~
```

### How many tokens a user ends up with

Every successful login issues a refresh token and stores it, so a user signing in from a phone, a
laptop and a browser tab has three, and signing in again from any of them adds a fourth. This is
deliberate: they are separate sessions, and each has to be able to expire, be refreshed, or be
revoked without disturbing the others. There is no reuse of an existing token, which would tie those
sessions together and, with `single_use` on, log one device out whenever another refreshed.

So the table grows with logins, and it is meant to be emptied on a schedule rather than kept small
by the bundle. [`gesdinet:jwt:clear`](#revoke-all-invalid-tokens) removes the expired ones and is
worth running [on a schedule](#revoke-all-invalid-tokens), by cron or by Symfony Scheduler.

A user can also be held to a number of them:

```yaml
gesdinet_jwt_refresh_token:
    max_tokens_per_user: 5
```

Signing in a sixth time revokes the session that has gone longest without being refreshed, so the
oldest device is signed out rather than the newest being refused. The count is taken after the new
token is stored, so the login that has just succeeded is one of the five and a limit of `1` leaves a
user signed in on one device at a time.

Newest is by expiry rather than by creation, which with [`ttl_update`](#update-token-ttl) on means
least recently used. Tokens that have already expired sort last and go first, so a user at the limit
never gives up a live session while a dead one is kept.

There is no limit unless one is set. It needs a manager implementing
`Gesdinet\JWTRefreshTokenBundle\Model\RevokeRefreshTokenManagerInterface`, which all three of the
bundle's own do; a [manager of your own](#store-the-tokens-somewhere-else-entirely) has to implement
it too, and is told so rather than left quietly enforcing nothing.

What that does not cover is somebody hammering the login endpoint to insert rows on purpose. That is
not something to solve here — a row is exactly what a successful login is supposed to leave behind —
it belongs on the endpoint, where Symfony already has the tool:

```yaml
security:
    firewalls:
        login:
            login_throttling:
                max_attempts: 5
```

Note that this limits failed attempts. Where repeated _successful_ logins are the concern, rate
limit the route itself with `symfony/rate-limiter`.

#### Showing a user their sessions

`Gesdinet\JWTRefreshTokenBundle\Model\ListRefreshTokenManagerInterface` reads them back, aliased to
the manager so it can be injected by type:

```php
public function __construct(
    private readonly ListRefreshTokenManagerInterface $refreshTokens,
    private readonly RefreshTokenManagerInterface $manager,
) {
}

public function sessions(UserInterface $user): array
{
    return array_filter(
        $this->refreshTokens->findAllForUser($user),
        static fn (RefreshTokenInterface $token): bool => $token->isValid()
    );
}
```

They come back with the one expiring last first, which is the order
`max_tokens_per_user` keeps from, so the top of the list is the session that survives longest.
Tokens that have already expired are included — they are still rows in the table — and `isValid()`
is what tells them apart, as above.

Revoking one of them is `delete()` on the manager, and revoking all of them is
[`revokeAllForUser()`](#revoke-every-token-of-a-user).

Adding what the session was, a device name or the like, means [a token class of your
own](#use-another-class-for-refresh-tokens) with the column on it, and a decorator around
`gesdinet_jwt_refresh_token.refresh_token_generator` that fills it in. Bear in mind that anything
the client tells you about itself is worth exactly what the client is worth: it is useful for a
person recognising a session of their own and revoking it, and it is not a security control.

### Blocking the JWT a refresh replaces

A JWT is verified by its signature and its expiry, with nothing consulted in between, so refreshing
used to leave the previous one usable for the rest of its lifetime.
LexikJWTAuthenticationBundle 3 can withdraw one, and this hands it the JWT being replaced:

```yaml
lexik_jwt_authentication:
    blocklist_token:
        enabled: true
        cache: cache.app

gesdinet_jwt_refresh_token:
    block_previous_jwt: true
```

Two cases are left alone deliberately:

* **A request carrying no JWT.** Refreshing does not require one, and a client that discards its JWT
  before refreshing is the ordinary shape. There is nothing to block.
* **A JWT that no longer parses**, which for an expired one is the point: it is refused everywhere
  already, so recording it would fill the store to no end. What this catches is the JWT that is
  still valid — a client refreshing before expiry, which is what
  [`return_expiration`](#return-expiration-timestamp) encourages — where the old one would otherwise
  keep working until it expired on its own.

The blocklist is consulted on every authenticated request, so its store has to be one your
application shares between processes. Lexik's `cache` option is where that is chosen.

### Storing hashes instead of tokens

A refresh token gets its holder back into an account without a password. Stored as it is, a copy of
the table is a copy of everybody's credentials — which is why the passwords next to it are hashed.

```yaml
gesdinet_jwt_refresh_token:
    hash_tokens:
        enabled: true
```

The client still receives the token; what the database holds is `sha256$` followed by its hash, and
a refresh hashes what arrives to find the row. A leaked table cannot be used, because the values in
it are not the ones the endpoint accepts.

SHA-256 rather than bcrypt or argon2 on purpose. Slow hashing exists to make guessing expensive, and
a token of 64 random bytes is not guessable. It also has to be deterministic: a refresh request
carries the token and nothing else, so the row has to be findable from it, which rules out a
per-row salt.

**Turning it on does not sign anyone out.** Tokens already in the table are looked up as they are and
rewritten hashed the first time they are used. Once they have all expired — a `ttl` after you turned
this on — close that door:

```yaml
gesdinet_jwt_refresh_token:
    hash_tokens:
        enabled: true
        accept_stored_in_the_clear: false
```

Until you do, a token read from a backup taken before the change would still work.

One consequence worth knowing: `getRefreshToken()` on a token read back from storage returns the
hash, since the hash is what is stored. The value the client is given exists only at the moment it
is issued. Anything of yours that expects to read the token back out of the database has to be
looked at before turning this on.

### Single Use Tokens

You can configure the refresh token so it can only be consumed _once_. If set to `true` and the refresh token is consumed, a new refresh token will be provided.
To enable this behavior add this line to your config:

```yaml
gesdinet_jwt_refresh_token:
    single_use: true
```

#### Ending the chain of single use tokens

Each token issued in place of a single use one starts its ttl over, so a user refreshing before the
current token expires never has to sign in again. Turn the update off to have the replacement expire
when the token it replaces would have:

```yaml
gesdinet_jwt_refresh_token:
    single_use: true
    single_use_ttl_update: false
```

Refreshing then keeps rotating the token, and the whole chain ends a `ttl` after the first one was
issued, at which point the user signs in again. It is left on by default, which is how the bundle has
always behaved.

### Set the refresh token in a cookie

By default, the refresh token is returned in the body of a JSON response. You can use the following configuration to set it in a HttpOnly cookie instead. The refresh token is automatically extracted from the cookie during refresh.
To allow users to logout when using cookies, you need to [configure the `LogoutEvent` to trigger on a specific route](#invalidate-refresh-token-on-logout), and call that route during logout.

The cookie is named after [`token_parameter_name`](#refresh-token-parameter-name), so it is
`refresh_token` unless you change that.

`same_site` takes `none`, `lax` or `strict`, matched without regard to case, or an empty value to
leave the attribute off the cookie entirely. Every one of these settings can be read from an
environment variable:

```yaml
gesdinet_jwt_refresh_token:
    cookie:
        enabled: true
        same_site: '%env(COOKIE_SAME_SITE)%'
```

```yaml
gesdinet_jwt_refresh_token:
    cookie:
      enabled: true
      same_site: lax               # default value
      path: /                      # default value
      domain: null                 # default value
      http_only: true              # default value
      secure: true                 # default value
      partitioned: false           # default value
      remove_token_from_body: true # default value
```

### Invalidate refresh token on logout

This bundle automatically registers an `EventListener` which triggers on `LogoutEvent`s from a specific firewall (default: `api`).
The `LogoutEventListener` automatically invalidates the given refresh token and, if enabled, unsets the cookie.
If no refresh token is supplied, an error is returned and the cookie remains untouched. If the supplied refresh token is (already) invalid, the cookie is unset.
All you have to do is make sure the `LogoutEvent` triggers on a specific route, and call that route during logout:

```yaml
# config/packages/security.yaml
security:
    firewalls:
        api:
            logout:
                path: api_token_invalidate
```

```yaml
# config/routes.yaml
api_token_invalidate:
    path: /api/token/invalidate
```

A logout only invalidates the token of whoever is logging out. When the request carries an
authenticated user and the refresh token belongs to somebody else, it is left alone and answered as
one that no longer exists, so the endpoint cannot be asked whether another user's token is still
live. A request with no authenticated user, which is what logging out with an expired access token
looks like, invalidates the token it carries.

If you want to configure the `LogoutEvent` to trigger on a different firewall, the name of the firewall has to be configured:

```yaml
# config/packages/security.yaml
security:
    firewalls:
        myfirewall:
            logout:
                path: api_token_invalidate
```

```yaml
# config/routes.yaml
api_token_invalidate:
    path: /api/token/invalidate
```

```yaml
# config/packages/gesdinet_jwt_refresh_token.yaml
gesdinet_jwt_refresh_token:
    logout_firewall: myfirewall
```

### Doctrine Manager Type

By default, the bundle will try to set the appropriate Doctrine object manager for your application using the following logic to define the manager type:

* If the `manager_type` configuration key is set to "mongodb", the MongoDB ODM is used
* If the `manager_type` configuration key is set to "orm" (default), and the ORM is not installed but the MongoDB ODM is installed, the MongoDB ODM is used
* By default, the `manager_type` is "orm" and the ORM is used
You can customize the manager type using the `manager_type` configuration:

```yaml
gesdinet_jwt_refresh_token:
    manager_type: mongodb
```

### Use another object manager

You can configure the bundle to use any object manager using the `object_manager` configuration. Note, an explicitly defined `object_manager` configuration will override any automatic configuration based on the `manager_type`.

```yaml
gesdinet_jwt_refresh_token:
    object_manager: my.specific.entity_manager.id
```

### Store the tokens through DBAL, without an object manager

The bundle can persist the refresh tokens straight through a Doctrine DBAL connection, without the
ORM or the ODM. The tokens are read and written with plain queries, so nothing is hydrated into a
unit of work and no life-cycle event is raised for them.

```yaml
gesdinet_jwt_refresh_token:
    refresh_token_class: App\Entity\RefreshToken
    dbal_connection: doctrine.dbal.default_connection
    dbal_table_name: refresh_tokens
```

`dbal_connection` and `object_manager` are mutually exclusive: the bundle uses one backend or the
other.

The table and column names are validated as SQL identifiers, so they may only hold letters, digits
and underscores, and may not start with a digit.

#### Custom column names

Point the bundle at an existing table by naming its columns:

```yaml
gesdinet_jwt_refresh_token:
    dbal_connection: doctrine.dbal.default_connection
    dbal_table_name: user_refresh_tokens
    dbal_columns:
        id:
            name: token_id
            type: integer
        refreshToken:
            name: token
            type: string
        username:
            name: user_identifier
            type: string
        valid:
            name: expires_at
            type: datetime
```

#### Creating the table

Create the table with a migration, like any other table.

For cases where that is not practical, the bundle can create it on the first request instead:

```yaml
gesdinet_jwt_refresh_token:
    dbal_auto_create_table: true
```

It is off by default, and worth understanding before turning it on. It runs DDL while the
application is serving traffic, which means the connection the application runs on needs rights to
alter the schema. A failure stops the request with an explanation rather than being logged and
forgotten, since a connection that cannot create the table would otherwise fail later on something
unrelated.

### Document the refresh token in API Platform

LexikJWTAuthenticationBundle documents the login endpoint for API Platform, but its response schema
only carries the JWT: the refresh token beside it is added by this bundle, and that factory has no
hook to extend. The refresh endpoint is not documented by anyone, since it is a firewall
authenticator rather than a resource, so there is no controller for API Platform to find.

Turning this on completes both:

```yaml
gesdinet_jwt_refresh_token:
    api_platform:
        enabled: true
```

It reads the configuration the bundle already holds, so the specification follows it rather than
having to be kept in step by hand. That includes the case worth spelling out: with the cookie
enabled and `remove_token_from_body` left on, there is no `refresh_token` property in the response
at all, and documenting one would promise a field that never arrives. The refresh endpoint is then
documented as taking no body, since the browser carries the cookie.

The refresh paths come from the firewalls, so every firewall the authenticator is enabled on gets
its endpoint documented.

It is off by default. An application already decorating `api_platform.openapi.factory` by hand
would otherwise document the same endpoint twice, so turning this on is the moment to remove that
decorator.

### Store the tokens somewhere else entirely

Name a service of your own and the bundle wires nothing of its own storage, so it does not need
Doctrine installed at all. This is the answer when the tokens live in a PDO repository, a cache, an
API, or a table the application already owns and manages itself.

```yaml
gesdinet_jwt_refresh_token:
    refresh_token_class: App\Security\RefreshToken
    refresh_token_manager: App\Security\PdoRefreshTokenManager
```

The service implements `Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface`, which
is the whole of what the bundle asks of storage: read a token by its string, read the last one
issued to a user, save one, delete one and report whether a row went, and revoke the expired ones.
The token class itself implements `Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface` and
is tied to nothing, so `Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken` can be used as it is.

`refresh_token_manager` replaces the manager the bundle would build, so `object_manager` and
`dbal_connection` have nothing left to configure and cannot be combined with it.

Two details worth honouring, because the bundle relies on them and its own managers are tested
against them:

* `delete()` returns the number of rows actually removed, `0` when the token was not there. Two
  requests racing to spend the same single use token both read it back first, and this is what
  tells the one that lost that it deleted nothing.
* `revokeAllInvalidBatch()` removes each batch before reading the next, so the remaining tokens
  shift down and the offset stays where it is. Paging it forward skips a batch for every batch
  removed.

`tests/Services/InMemoryRefreshTokenManager.php` is a complete implementation, around a hundred
lines storing the tokens in an array, and it is run against the same test suite as the three
shipped managers.

### Revoke every token of a user

Refresh tokens are stored against the user identifier, the value `getUserIdentifier()` returns, and
that is what the bundle looks the user up with when a token is refreshed.

So when the identifier itself changes, for instance an application where it is the email address and
the user edits it, the tokens issued before still hold the old value and no longer resolve to
anybody. The same applies to any change that should end the existing sessions, such as a password
reset or an account being disabled.

Revoke them as part of that change:

```php
use Gesdinet\JWTRefreshTokenBundle\Model\RevokeRefreshTokenManagerInterface;

public function __construct(
    private RevokeRefreshTokenManagerInterface $refreshTokenManager,
) {
}

public function changeEmail(User $user, string $email): void
{
    // Before the change, while the user still carries the identifier the tokens were issued for
    $this->refreshTokenManager->revokeAllForUser($user);

    $user->setEmail($email);
}
```

It returns how many were revoked, and deletes them in the database, so no token is loaded into
memory and no life-cycle event is raised for them.

The alternative is an identifier that never changes, a numeric id or a UUID, with the email looked up
separately when signing in. That keeps the tokens valid across an email change, and is a decision
about the application rather than about this bundle.

Revoking by user is available with the Doctrine ORM and MongoDB ODM backends.

### Use another class for refresh tokens

You can define your own refresh token class for your project by creating a class extending from the classes provided by this bundle. This also allows you to customize the refresh token, i.e. to add extra data to the token.
When using the Doctrine ORM, create a class extending `Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken` in your application:

```php
<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
/**
 * This class extends Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken to have another table name.
 */
#[ORM\Table('jwt_refresh_token')]
class JwtRefreshToken extends RefreshToken
{
}
```

When using the Doctrine MongoDB ODM, create a class extending `Gesdinet\JWTRefreshTokenBundle\Document\RefreshToken` in your application:

```php
<?php
namespace App\Document;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Gesdinet\JWTRefreshTokenBundle\Document\RefreshToken;
/**
 * This class extends Gesdinet\JWTRefreshTokenBundle\Document\RefreshToken to have another collection name.
 */
#[ODM\Document(collection: 'jwt_refresh_token')]
class JwtRefreshToken extends RefreshToken
{
}
```

Then declare this class adding this line to your config.yml file:

```yaml
gesdinet_jwt_refresh_token:
    refresh_token_class: App\Entity\JwtRefreshToken
```

_NOTE_ If using another object manager, it is recommended your object class extends from `Gesdinet\JWTRefreshTokenBundle\Model\AbstractRefreshToken` which implements all required methods from `Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface`.

#### Mapping the entity yourself

`Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken` is a mapped superclass, so a class extending
it inherits the mapping, including the identifier and its `AUTO` generation strategy. That is the
usual thing to want, but it cannot be overridden from the subclass: Doctrine refuses an identifier
declared twice, with `Duplicate definition of column 'id'`. `#[ORM\AttributeOverride]` does not
reach it either, since it overrides a column definition and the generation strategy is not part of
one.

This matters on PostgreSQL, where Doctrine deprecates relying on `AUTO` and asks for the strategy to
be chosen explicitly.

Extend `Gesdinet\JWTRefreshTokenBundle\Model\AbstractRefreshToken` instead. It carries no mapping
at all, so the whole of it is yours to declare:

```php
<?php
namespace App\Entity;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository;
use Gesdinet\JWTRefreshTokenBundle\Model\AbstractRefreshToken;

#[ORM\Entity(repositoryClass: RefreshTokenRepository::class)]
#[ORM\Table(name: 'refresh_tokens')]
class RefreshToken extends AbstractRefreshToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    protected int|string|null $id = null;

    #[ORM\Column(name: 'refresh_token', type: 'string', length: 128, unique: true)]
    protected ?string $refreshToken = null;

    #[ORM\Column(type: 'string', length: 255)]
    protected ?string $username = null;

    #[ORM\Column(type: 'datetime')]
    protected ?DateTimeInterface $valid = null;
}
```

Point `refresh_token_class` at it as above. The properties are redeclared so the attributes have
something to sit on; they are the same properties, not new ones.

The repository class is worth keeping: the bundle's manager uses `findInvalidBatch()` from it to
revoke expired tokens in batches.

An XML mapping does the same, and is the way to keep the entity itself free of mapping:

```xml
<entity name="App\Entity\RefreshToken"
        repository-class="Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository"
        table="refresh_tokens">
    <id name="id" type="integer">
        <generator strategy="SEQUENCE"/>
        <sequence-generator sequence-name="refresh_tokens_id_seq" allocation-size="100" initial-value="1"/>
    </id>
    <field name="refreshToken" type="string" column="refresh_token" length="128" unique="true"/>
    <field name="username" type="string" length="255" column="username"/>
    <field name="valid" type="datetime"/>
</entity>
```

Taking the mapping over means keeping it: a field added to the model in a later release has to be
added here too, and the release notes will say so.

### Generating Tokens

When you authenticate through /api/login_check with user/password credentials, LexikJWTAuthenticationBundle now returns a JWT Token and a Refresh Token data.

```json
{
  "token": "eyxxxGciOiJSUzI1NiIsInR5cCI6IkpXUyJ9.eyJleHAiOjE0NDI0MDM3NTgsImVtYWlsIjoid2VibWFzdGVyQGdlc2RpbmV0LmNvbSIsImlhdCI6IjE0NDI0MDM3MzgifQ.bo5pre_v0moCXVOZOj-s85gVnBLzdSdsltPn3XrkmJaE8eaBo_zcU2pnjs4dUc9hhwNZK8PL6SmSNcQuTUj4OMK7sUDfXr62a05Ds-UgQP8B2Kpc-ZOmSts_vhgo6xJNCy8Oub9-pRA_78WzUUxt294w0IArrNlgQAGewk65RSMThOif9G6L7HzBM4ajFZ-kMDypz2zVQea1kry-m-XXKNDbERCSHnMeV3rANN48SX645_WEvwaHy0agChR4hTnThzLof2bShA7j7HmnSPpODxQszS5ZBHdMgTvYhlcWJmwYswCWCTPl3lsqVq_UOFI5_4arpSNlUwZsichqxXVAHX5idZqCWtoaqAbvNQe2IpinYajoXw-MlYKvcN2TLUF_8sy529olLUagf4FCpCO6JFxovv0E7ll9tUOVvx9LlannqV8976q5XCOoXszKonZSH7DhsBlW5Emjv7PailbARZ-hfl4YlamyY2QbnxAswYycfoxqJxbbIKYGA8dlebdvMyC7m9VATnasTuKeEKS3mP5iyDgWALBHNYXm1FM-12zHBdN3PbOgxmy_OBGvk05thYFEf2WVmyedtFHy4TGlI0-otUTAf2swQAXWhKtkLWzokWWF7l5iNzam1kkEgql5EOztXHDZpmdKVHWBVNvN3J5ivPjjJBm6sGusf-radcw",
  "refresh_token": "xxx00a7a9e970f9bbe076e05743e00648908c38366c551a8cdf524ba424fc3e520988f6320a54989bbe85931ffe1bfcc63e33fd8b45d58564039943bfbd8dxxx"
}
```

The refresh token is persisted as a `RefreshTokenInterface` object. When your JWT expires, you have two options:

* Generate a new JWT by re-authenticate with your credentials via `/api/login_check`. This will also generate a new refresh token.
* Generate a new JWT by POSTing your valid refresh token to `/api/token/refresh`. This method does not require any user credentials. A refresh token can be used as long as it is not expired - it even can be used multiple times (*). On a successful refresh, the refresh tokens TTL will increase, but the refresh token itself will not change.
_**(\*) Note that when a refresh token is consumed and the config option `single_use` is set to `true` the token will no longer be valid.**_

```bash
curl -X POST -d refresh_token="xxxx4b54b0076d2fcc5a51a6e60c0fb83b0bc90b47e2c886accb70850795fb311973c9d101fa0111f12eec739db063ec09d7dd79331e3148f5fc6e9cb362xxxx" 'http://xxxx/token/refresh'
```

This call returns a new valid JWT token renewing valid datetime of your refresh token.

### Issuing a token from your own code

Creating a JWT [programmatically](https://github.com/lexik/LexikJWTAuthenticationBundle/blob/3.x/Resources/doc/7-manual-token-creation.rst)
does not produce a refresh token, because the refresh token is attached by a listener on the
authentication success event rather than by the JWT manager.

**In a controller**, dispatching that event yourself is what applies the whole configuration — the
ttl, `single_use`, the cookie and all of its settings, `return_expiration`, and
`remove_token_from_body`:

```php
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;

$data = ['token' => $this->jwtManager->create($user)];
$response = new JsonResponse($data);

$event = new AuthenticationSuccessEvent($data, $user, $response);
$this->eventDispatcher->dispatch($event, Events::AUTHENTICATION_SUCCESS);

// The listener adds the refresh token to the data and the cookie to the response
$response->setData($event->getData());

return $response;
```

**Outside a request** — a console command, a message handler — that listener does nothing, on
purpose: it reads the incoming request to see whether a token is being replaced, and there is no
response to put a cookie on. Create the token directly:

```php
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

public function __construct(
    private readonly RefreshTokenGeneratorInterface $generator,
    private readonly RefreshTokenManagerInterface $manager,
    #[Autowire('%gesdinet_jwt_refresh_token.ttl%')]
    private readonly int $ttl,
) {
}

public function issueFor(UserInterface $user): string
{
    $refreshToken = $this->generator->createForUserWithTtl($user, $this->ttl);

    $this->manager->save($refreshToken);

    return (string) $refreshToken->getRefreshToken();
}
```

Injecting the configured `ttl` rather than a constant of your own keeps tokens made this way in step
with the ones the bundle issues.

## Useful Commands

### Revoke all invalid tokens

If you want to revoke all invalid refresh tokens, where the expiration time has passed, you can run this command:

```bash
php bin/console gesdinet:jwt:clear
```

The command optionally accepts a date argument which will delete all tokens older than the given time. This can be any value that can be parsed by the `DateTime` class.

```bash
php bin/console gesdinet:jwt:clear 2015-08-08
```

You can also specify the batch size used by the command when clearing tokens with the `--batch-size` option, which defaults to the `default_invalid_batch_size` config option when not provided.

```bash
php bin/console gesdinet:jwt:clear --batch-size=2500
```

The command reports how many tokens it revoked. Add `-v` to list them, which is worth leaving off
when clearing a backlog of thousands.

```bash
php bin/console gesdinet:jwt:clear -v
```

Something has to run it on a schedule. Nothing in the bundle clears the table on its own, so left
alone it grows with every login for as long as the application is up.

A cron job is one way:

```cron
0 3 * * * /usr/bin/php /path/to/app/bin/console gesdinet:jwt:clear --no-interaction
```

If you already use [Symfony Scheduler](https://symfony.com/doc/current/scheduler.html), it is
usually the better home for this: it lives with the code, it is deployed with the code, and it does
not need access to the crontab of whatever the application runs on — which is why the cron job so
often never gets set up.

```php
namespace App\Scheduler;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule('default')]
final class MaintenanceSchedule implements ScheduleProviderInterface
{
    public function __construct(private CacheInterface $cache)
    {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(RecurringMessage::cron('0 3 * * *', new RunCommandMessage('gesdinet:jwt:clear')))
            // Without this two workers both run it, which is harmless here but rarely is elsewhere
            ->lock($this->cache->getItem('maintenance-schedule'));
    }
}
```

Either way it needs a worker or a cron daemon actually running. A schedule nothing consumes is the
same as no schedule, and the symptom is a table that quietly grows for months.

### Revoke a token

If you want to revoke a single token you can use this command:

```bash
php bin/console gesdinet:jwt:revoke TOKEN
```

## Events

### Token Refreshed

When a token is refreshed, the `gesdinet.refresh_token` event is dispatched with a `Gesdinet\JWTRefreshTokenBundle\Event\RefreshEvent` object.

### Refresh Token Failure

When there is a failure authenticating the refresh token, the `gesdinet.refresh_token_failure` event is dispatched with a `Gesdinet\JWTRefreshTokenBundle\Event\RefreshAuthenticationFailureEvent` object.

### Refresh Token Not Found

When there is a failure authenticating the refresh token, the `gesdinet.refresh_token_not_found` event is dispatched with a `Gesdinet\JWTRefreshTokenBundle\Event\RefreshTokenNotFoundEvent` object.

### Adding your own data to the response

The refresh token is put into the response by a listener on Lexik's
`lexik_jwt_authentication.on_authentication_success`, and anything else can go in beside it the same
way:

```php
namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\User\UserInterface;

#[AsEventListener('lexik_jwt_authentication.on_authentication_success')]
final class AttachUserToTheResponse
{
    public function __invoke(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof UserInterface) {
            return;
        }

        $data = $event->getData();
        $data['user'] = ['nickname' => $user->getNickname()];

        $event->setData($data);
    }
}
```

That event is dispatched on **both** signing in and refreshing, which is usually what you want: a
client that gets the user with its token on login gets it again with the refreshed one, rather than
having to remember it. It is by design rather than a quirk of any one configuration.

Where something should happen only on a refresh, listen to
[`gesdinet.refresh_token`](#token-refreshed) instead — it is dispatched only then, and carries the
refresh token itself.

## Token Extractor

The bundle provides a `Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface` to define classes which can read the refresh token from the request.
By default, the `Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ChainExtractor` is used which allows checking multiple aspects of the request for a token. The first token found will be used.
You can create a custom extractor by adding a class to your application implementing the interface. For example, to add an extractor checking for a "X-Refresh-Token" header:

```php
<?php
namespace App\Request\Extractor;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use Symfony\Component\HttpFoundation\Request;
final class HeaderExtractor implements ExtractorInterface
{
    public function getRefreshToken(Request $request, string $parameter): ?string
    {
        return $request->headers->get('X-Refresh-Token');
    }
}
```

This bundle handles automatically configuring `ExtractorInterface` objects and will automatically set the `gesdinet_jwt_refresh_token.request_extractor` container tag when your application uses autoconfiguration (`autoconfigure: true` in your `services.yaml` file). If autoconfiguration is not in use, you will need to manually configure the tag:

```yaml
services:
    App\Request\Extractor\HeaderExtractor:
        tags:
            - { name: gesdinet_jwt_refresh_token.request_extractor }
```

The body extractor reads a JSON body whatever the request declares its content type to be, so a
client that sets no `Content-Type`, which is what `fetch()` does when it is given no headers, is
still understood. A body that is not JSON, or that does not hold the parameter, is passed over for
the next extractor rather than failing.

### Prioritizing Extractors

The `gesdinet_jwt_refresh_token.request_extractor` container tag supports prioritizing extractors, you can use this to set the preferred order for your extractors by adding a `priority` attribute. The higher the number, the sooner the extractor will be run.

```yaml
services:
    App\Request\Extractor\HeaderExtractor:
        tags:
            - { name: gesdinet_jwt_refresh_token.request_extractor, priority: 25 }
```

## Logout

The bundle listens for Symfony's `LogoutEvent` and invalidates the refresh token when it fires, as
described in [Invalidate refresh token on logout](#invalidate-refresh-token-on-logout) above. It has
one option of its own, on the `refresh_jwt` authenticator:

```yaml
# config/packages/security.yaml
security:
    firewalls:
        api:
            refresh_jwt:
                check_path: /token/refresh
                invalidate_token_on_logout: true # default value
```

Turn it off to keep the refresh tokens alive across a logout, so that other sessions of the same
user survive it.

Everything else about how logging out behaves belongs to the firewall rather than to this bundle:
the path, where to send the user afterwards, clearing cookies or site data, invalidating the session
and CSRF protection are all
[Symfony's logout options](https://symfony.com/doc/current/security.html#logging-out), configured
under `security.firewalls.<name>.logout`:

```yaml
# config/packages/security.yaml
security:
    firewalls:
        api:
            logout:
                path: api_token_invalidate
                delete_cookies: ['refresh_token']
                clear_site_data: ['cookies', 'storage']
```

Putting any of those under `gesdinet_jwt_refresh_token` fails with `Unrecognized option`, since the
bundle has no `logout` node.

For additional details on configuring the JWTRefreshTokenBundle, refer to the
[main documentation](https://github.com/markitosgv/JWTRefreshTokenBundle) or other sections of this
repository.
