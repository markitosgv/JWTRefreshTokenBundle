# Upgrade from 2.2 to 3.0

This release raises what it runs on and takes two things away. Read "Requirements" first: if your
application does not meet them, nothing else here applies and 2.2 remains supported.

Everything else is one configuration option that is now required, and classes that can no longer be
extended.

## Requirements

| | 2.2 | 3.0 |
| --- | --- | --- |
| PHP | 8.2 | **8.4** |
| Symfony | 6.4, 7.x, 8.0 | **8.0** |
| LexikJWTAuthenticationBundle | 2.15 or 3.x | **3.x** |
| doctrine/dbal, if used | 3.7 or 4.x | **4.x** |

Symfony 8 requires PHP 8.4, which is why the PHP minimum moves with it.

**If you are on Symfony 6.4 or 7.x, stay on 2.2.** It is maintained, and Symfony 7.4 is supported
until November 2028. There is nothing in this release that is unsafe to be without; the reason it
exists is `block_previous_jwt`, which needs Lexik 3.

Upgrading Lexik from 2.x to 3.x is its own step, with its own upgrade notes. Do that first, on 2.2,
and confirm your application still works before coming here.

## `check_path` is now required

```yaml
security:
    firewalls:
        api:
            refresh_jwt:
                check_path: /api/token/refresh   # or the route name
```

It used to default to `/login_check`, which is Lexik's _login_ path and never the right one for
refreshing. Left at the default the authenticator took over no requests at all, the request carried
on to the router, and Symfony reported the refresh route as having no controller — a message that
names the route while the route is fine.

That default cost enough people enough time (#330, #307, and half of #255) that it is better as a
configuration error. If your `refresh_jwt` has no `check_path`, the container will now refuse to
build and say so. Whatever path your refresh route is on is the value.

## Classes that are now `final`

* `Gesdinet\JWTRefreshTokenBundle\GesdinetJWTRefreshTokenBundle`
* `Gesdinet\JWTRefreshTokenBundle\Http\RefreshAuthenticationFailureResponse`
* `Gesdinet\JWTRefreshTokenBundle\Security\Http\Authenticator\Token\PostRefreshTokenAuthenticationToken`
* `Gesdinet\JWTRefreshTokenBundle\Exception\InvalidRefreshTokenException`
* `Gesdinet\JWTRefreshTokenBundle\Exception\UnknownRefreshTokenException`
* `Gesdinet\JWTRefreshTokenBundle\Exception\UnknownUserFromRefreshTokenException`
* `Gesdinet\JWTRefreshTokenBundle\Security\Exception\InvalidTokenException`
* `Gesdinet\JWTRefreshTokenBundle\Security\Exception\MissingTokenException`
* `Gesdinet\JWTRefreshTokenBundle\Security\Exception\TokenNotFoundException`

Catching those exceptions is unaffected; only extending them is.

**What is deliberately still extendable**, because it is how the bundle is meant to be used:

* `Model\AbstractRefreshToken`, `Entity\RefreshToken` and `Document\RefreshToken` — bringing your own
  token class is the documented path.
* `Entity\RefreshTokenRepository` and `Document\RefreshTokenRepository` — likewise for a repository.

## `RefreshEvent` carries the request

```php
public function __construct(
    RefreshTokenInterface $refreshToken,
    TokenInterface $token,
    ?string $firewallName,
    Request $request,      // new, and required
) {
```

Only code constructing the event itself is affected, which is unusual — it is dispatched by the
bundle. Listeners gain `$event->getRequest()`, which is the request the refresh was made with, so
reaching for the request stack to find the same thing is no longer necessary.

`$firewallName` also loses its default. It was optional and always passed.

## Doctrine DBAL 3

Dropped, along with the two shims that supported it. Nothing to do beyond having DBAL 4, which
Symfony 8 applications will have.

## `dbal_columns` has to name the `id` column

Only if you configure it. A map without an `id` was accepted and produced a table whose expired
tokens could never be revoked: batches are deleted by identifier, so with none to delete by the
command read the same batch forever. It is a configuration error now.

```yaml
gesdinet_jwt_refresh_token:
    dbal_columns:
        id:
            name: token_id
            type: integer
        # ...
```

## DBAL index names now include the table name

Only affects the DBAL backend, and only tables created from now on.

`refresh_tokens` used to get its indexes named `UNIQ_REFRESH_TOKEN`, `IDX_USERNAME` and `IDX_VALID`
whatever it was called. Those names are scoped to the schema on PostgreSQL and to the database on
SQLite, so having the bundle manage a second table was impossible — creating it failed with
`index UNIQ_REFRESH_TOKEN already exists`, which names an index and nothing else. They are now
`UNIQ_REFRESH_TOKEN_REFRESH_TOKENS`, `IDX_USERNAME_REFRESH_TOKENS` and `IDX_VALID_REFRESH_TOKENS`,
and past 63 characters — PostgreSQL's identifier limit — the table part is hashed instead.

**Existing tables are left alone.** The table is only built when it is absent, so nothing renames an
index you already have, and nothing needs to. It matters if you assert on index names in a test, or
if a migration of yours refers to one.

## New: blocking the JWT a refresh replaces

The reason this release requires Lexik 3.

```yaml
lexik_jwt_authentication:
    blocklist_token:
        enabled: true
        cache: cache.app

gesdinet_jwt_refresh_token:
    block_previous_jwt: true
```

Off by default. A JWT is verified by its signature and its expiry with nothing consulted in between,
so refreshing used to leave the previous one usable for the rest of its lifetime. Lexik 3's blocklist
is what makes withdrawing it possible.

Two cases are left alone on purpose: a request carrying no JWT has nothing to block, and a JWT that
no longer parses is refused everywhere already, so recording an expired one would only fill the
store. What this catches is the JWT that is still valid, which is the case that matters — a client
refreshing before expiry, where the old token would otherwise keep working.

The blocklist is consulted on every authenticated request, so its store has to be shared between
your processes. That is what Lexik's `cache` option chooses.

## Nothing else changed

Every other option, service and interface is as it was in 2.2. In particular `hash_tokens`,
`max_tokens_per_user`, `single_use_ttl_update`, `refresh_token_manager`, the DBAL backend and the
API Platform integration all arrived in 2.2 and behave the same here.
