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

## Refresh tokens have a family, and that means a migration

**This one needs a schema change before the application will run.** The token classes that ship with
the bundle gained a `family` column, and Doctrine reads every mapped field, so until the column is
there the first query fails.

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

The columns are `family VARCHAR(32) NULL` and `family_valid DATETIME NULL`. Existing rows keep a null
family, which is read as "this token belongs to no chain" — they carry on working, and the tokens
issued after them get families normally. For the DBAL backend, tables the bundle creates itself get
the columns, but a table already in place does not:

```sql
ALTER TABLE refresh_tokens ADD family VARCHAR(32) NULL, ADD family_valid DATETIME NULL;
```

MongoDB needs nothing; a missing field reads as null.

**What it is for.** A token issued in place of another carries the family of the one it replaced, so
a login and every refresh descending from it share one value. Without it the bundle holds a set of
tokens with nothing relating them, and "end this session" can only mean "delete this one token" —
which, if the client has refreshed since, is a token that no longer exists while the session carries
on. It is what `single_use` rotation needs to be more than a moving target.

Recommended, since every family lookup is a query on it — the mapped superclass cannot declare it,
so it goes on your entity:

```php
#[ORM\Entity]
#[ORM\Index(fields: ['family'])]
class RefreshToken extends BaseRefreshToken
{
}
```

**If you bring your own token class**, nothing changes and nothing breaks: families live in
`Model\FamilyAwareRefreshTokenInterface`, which is separate from `RefreshTokenInterface` precisely so
that a class written against the latter is untouched. To opt in, implement the interface, use
`Model\RefreshTokenFamilyTrait`, and map the property. Mapping it is the part that matters — an
unmapped property satisfies the interface and silently loses the value on every read, which is worse
than not having families at all.

**If you configure `dbal_columns`**, add `family` and `familyValid` entries to gain families, or
leave the map as it is to carry on without them. A map naming neither is taken to mean the table has
no chains and no query goes near either column; naming one and not the other describes a table half
the queries would fail against, so both are needed together.

## New: `max_session_lifetime`

```yaml
gesdinet_jwt_refresh_token:
    ttl: 3600                    # each token lasts an hour
    max_session_lifetime: 604800 # but the chain of them ends after a week
```

A `ttl` that starts over on every rotation means a session never ends: a client that keeps refreshing
keeps it alive forever. This is the ceiling on that, and it is a different thing from `ttl` — one
bounds a token, the other bounds the chain.

The deadline is set when a chain starts and carried along it unchanged, so it means the same at the
hundredth refresh as at the first. A token whose `ttl` would outlive the chain has its expiry cut
back to it; one expiring sooner is left alone. Null, the default, is the behaviour the bundle has
always had.

Stored in a `family_valid` column, which is part of the same migration as `family` above. It needs a
token class with families.

**`single_use_ttl_update: false` is the older way of doing this** and still works. It ends the chain
when the first token would have expired, so the ceiling and the ttl are forced to be the same
number. `max_session_lifetime` separates them, which is usually what people wanted: a short-lived
token that rotates often, inside a session that ends on a schedule of its own.

## New: withdrawing a user's JWTs when their sessions are revoked

Off by default.

```yaml
gesdinet_jwt_refresh_token:
    block_jwts_on_revocation:
        enabled: true
        cache: cache.app
        ttl: 3600            # at least your lexik_jwt_authentication.token_ttl
        user_claim: username # Lexik's user_id_claim
```

`revokeAllForUser()` is what you call after a password reset or when disabling an account, and on its
own it only takes the refresh tokens away: every JWT already issued keeps working until it expires.
That is the wrong outcome at exactly the moment it matters.

**Lexik's blocklist cannot do this**, which is worth saying plainly. It is keyed by `jti`, so it
withdraws a token you are holding — and the tokens that need withdrawing here are in clients, where
you cannot see them. What can be recorded instead is *when* the revocation happened, per user, and
any JWT whose `iat` is at or before that moment is refused on decode.

`ttl` only has to outlive the JWTs issued before the mark, which is why it is your JWT ttl rather
than your refresh token ttl. **Set it too short and the oldest of those tokens start being accepted
again**, which is the one way to get this wrong.

Deliberately narrow:

* Only `revokeAllForUser()`. `revokeAllButNewestForUser()` prunes older sessions while the user
  carries on with the newest, so marking there would sign them out of the device in their hand.
* `revokeFamily()` — ending one session — marks nothing. Nothing on a JWT says which chain issued it,
  so there is no way to refuse only that session's tokens.
* A payload without both the user claim and `iat` is left to the rest of the verification rather than
  refused, so an application whose tokens carry different claims is unaffected.

The mark is read on every authenticated request, so use a fast pool, and it has to be shared between
your processes: a local one leaves a revoked user signed in wherever the mark did not reach.

## New: listing and ending a user's sessions

`Gesdinet\JWTRefreshTokenBundle\Session\SessionLister` is registered with every Doctrine backend and
needs no configuration.

```php
public function __construct(private SessionLister $sessions)
{
}

#[Route('/account/sessions')]
public function list(#[CurrentUser] UserInterface $user, Request $request): Response
{
    // Passing the token the request came with marks that session as the current one
    return $this->json($this->sessions->forUser($user, $request->cookies->get('refresh_token')));
}

#[Route('/account/sessions/{id}', methods: ['DELETE'])]
public function end(#[CurrentUser] UserInterface $user, string $id): Response
{
    return $this->json(['revoked' => $this->sessions->end($user, $id)]);
}
```

A `Session` carries the chain id, when it stops being refreshable, when the chain itself runs out if
`max_session_lifetime` is set, how many of its tokens are still stored, and whether it is the one the
request came from.

**Why this rather than `findAllForUser()`.** That returns tokens, and with `single_use` a token is
replaced on every refresh — so the same browser appears as a new row each time it refreshes, one
session looks like a dozen devices, and the row you revoke is usually one that is already gone.
Grouping by chain is what turns the list into the thing a user recognises.

**`end()` checks the session is the caller's.** A chain is addressed by an identifier the client
hands back, and a session list is exactly where such an identifier is handed out; without the check
anybody who learnt one could sign a stranger out. A session that is not theirs and one that does not
exist give the same answer, so the call cannot be used to find out which chains exist.

Tokens stored before chains existed are shown with a null `id`, each on its own. They cannot be
ended individually, because nothing links them to anything; they go as they expire.

There is deliberately no device or browser label. A `User-Agent` changes on every browser update and
is whatever the client says it is, so a name built from one is neither stable nor evidence. If you
want one, put it on your own token class — [bringing your own](README.md) is already supported — and
you will know exactly what it is worth.

## New: storing the tokens in a cache

```yaml
gesdinet_jwt_refresh_token:
    cache_pool: cache.app   # instead of object_manager or dbal_connection
```

A refresh token has a natural expiry, which is the one thing a cache does without being asked. With
this there is no `gesdinet:jwt:clear` to schedule and no table to watch grow: an expired token is
gone because the pool dropped it.

**What it deliberately cannot do**, because a pool answers for keys you already hold and cannot be
enumerated:

* `getLastFromUsername()` throws rather than returning null, which would read as "this user has no
  tokens" — a different thing from "this cannot be known", and the answer a caller would act on.
* `revokeAllInvalid()` and `revokeAllInvalidBatch()` return nothing. Not a stub: there is genuinely
  nothing left to revoke.
* `ListRefreshTokenManagerInterface` and `RevokeRefreshTokenManagerInterface` are not implemented and
  **their aliases are removed**, so a service asking for one fails to wire rather than being handed a
  manager that throws when called. `gesdinet:jwt:revoke` and session listing are unavailable.
* `max_tokens_per_user` and `reuse_detection` are configuration errors alongside it. Both need to
  find a user's other tokens, and quietly ignoring them would leave you believing your sessions were
  bounded when they were not.

The pool has to be shared between your processes and it has to be persistent. Losing the pool is
losing every session, so an in-memory or per-machine one signs everybody out on deploy.

One behavioural difference worth knowing: an expired token is absent rather than present-and-invalid,
so a refresh with one fails as "token not found" rather than "token invalid".

## New: rate limiting the refresh endpoint

Off by default, and needs `composer require symfony/rate-limiter`.

```yaml
framework:
    rate_limiter:
        refresh:
            policy: sliding_window
            limit: 20
            interval: '1 minute'

gesdinet_jwt_refresh_token:
    rate_limiter:
        enabled: true
        limiter: limiter.refresh   # a limiter named "refresh" is the service "limiter.refresh"
        key: ip                    # or "token"
```

This endpoint trades a refresh token for a JWT with no password in between, which makes it worth
limiting on its own terms rather than leaving it to whatever protects the login form.

The limiter is consumed **before** the token is looked at, let alone looked up. A limit applied only
to requests that got as far as a query would not bound the work the endpoint does, and the time a
refusal took would tell a caller whether the token they sent exists.

**`key` is a real trade-off, not a detail.**

* `ip`, the default, protects the endpoint: one caller cannot hammer it whatever tokens they present.
  The cost is that everybody behind one address shares an allowance, which for a mobile network or an
  office is a lot of people. Behind a proxy it also needs `framework.trusted_proxies` set, or every
  request arrives from the same address and they all share one allowance.
* `token` gives each session its own allowance, so no legitimate client can be shut out by another.
  It bounds how fast one session may refresh and does nothing at all about a caller arriving with a
  different token every time.

A refused request is answered `429` with `Retry-After` in seconds, rather than the `401` every other
failure here gets: being refused for asking too often is not a credentials problem, and `401` tells a
client to go and get new ones — which is more requests, at an endpoint already saying it has had too
many.

## New: recognising a refresh token that was already spent

Off by default, and the reason families exist.

```yaml
gesdinet_jwt_refresh_token:
    single_use: true          # required
    reuse_detection:
        enabled: true
        cache: cache.app      # has to be shared between your processes
        ttl: 2592000          # how long a spent token stays recognisable
```

`single_use` rotates the token on every refresh, which sounds like it protects against a stolen one
and mostly does not: the thief's copy keeps working until the legitimate client happens to refresh,
and when that finally breaks it, nobody learns why. The missing half is noticing the replay. A spent
token is deleted, so presenting it again is indistinguishable from presenting a token that never
existed — both are "unknown" — and that is the one distinction worth having.

With this on, a spent token is remembered by digest for `ttl` seconds. If one comes back, the whole
chain it belonged to is revoked and `RefreshTokenReuseDetectedEvent` is dispatched on
`gesdinet.refresh_token_reuse_detected`.

```php
#[AsEventListener('gesdinet.refresh_token_reuse_detected')]
public function __invoke(RefreshTokenReuseDetectedEvent $event): void
{
    $this->logger->warning('A spent refresh token was presented again', [
        'user' => $event->getSpentToken()->username,
        'revoked' => $event->getRevokedTokens(),
        'ip' => $event->getRequest()->getClientIp(),
    ]);
}
```

**It cannot tell theft from a client racing itself**, and neither can anything else in the bundle:
two requests refreshing at once produce exactly the same evidence. The chain is revoked either way,
because that is the safe answer to the case that matters. What the event is for is the judgement the
bundle cannot make — an occasional reuse for one user reads differently from the same thing across
many, and only your application has the rest of the picture.

The token itself is never stored: what goes into the pool is a SHA-256 of it, keyed by that digest.
Cache keys turn up in file names, in `redis-cli KEYS` output and in profiler dumps, and a refresh
token is a credential.

`ttl` defaults to the same 30 days as the refresh token. Shorter leaves a window in which a replay
reads as an ordinary wrong token.

Turning it on without `single_use` is a configuration error: nothing would ever be spent, so nothing
could be recognised, and the option would read as protection while detecting nothing.

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
