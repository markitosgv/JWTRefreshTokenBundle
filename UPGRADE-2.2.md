# Upgrade from 2.1 to 2.2

Nothing has to be changed in an application using the bundle through its configuration and its
services, with one exception noted under "Configuration now rejected". Everything else is either
additive or a behaviour change that corrects something that was wrong.

## Bundle Requirements

Unchanged: PHP 8.2 or later.

Symfony 7.0 and 7.1 are now installable. `symfony/console` and `symfony/security-http` stopped at
`^7.2` while every other component allowed `^7.0`, and Composer resolves the intersection, so those
versions could not be installed at all. They are widened to match and 7.0 is covered by the test
matrix.

## Configuration now rejected

`ttl` must be a positive number of seconds. `0` and negative values are rejected where previously
they were accepted and produced a token that had already expired when it was handed over, so every
refresh made with one failed.

This is the one change that can stop a container from building. An application configured that way
had a refresh endpoint that never worked, so the failure is surfacing a problem rather than
introducing one. There is no value meaning "never expires"; a long-lived token is a large number of
seconds.

## An expired JWT can now be exchanged

With `jwt` and `refresh_jwt` on the same firewall, the JWT authenticator was reached first and
rejected an expired token before the refresh authenticator saw it. Symfony orders authenticators by
the priority each factory declares rather than by the order written on the firewall, and this one
sat below Lexik's. It now sits above it.

Nothing needs to change to benefit from this. Two things follow:

* A request to the refresh endpoint carrying an expired JWT in the `Authorization` header now
  refreshes instead of returning 401. This matters most when the JWT is in a cookie, since the
  browser sends it whether or not you want it to.
* Applications that split the refresh endpoint into its own firewall to work around this can
  collapse it back into one. Keeping two firewalls also still works.

The refresh authenticator only takes over requests matching its `check_path`, so every other request
authenticates exactly as before.

## Changed behaviour

These correct behaviour that was wrong. An application may still observe the difference:

* The refresh token cookie expires when the token inside it does, rather than a `ttl` from the moment
  it was set. The two only agreed because every token was issued with a full `ttl`, which
  `single_use_ttl_update` no longer guarantees.
* The refresh token is read from a JSON body whatever the request declares its content type to be. A
  client sending no `Content-Type`, which is what `fetch()` does when given no headers, or a proxy
  that strips it, used to be answered as though no token had been supplied.
* Logging out invalidates the refresh token of the user logging out. A token belonging to somebody
  else is answered as one that no longer exists. A request with no authenticated user still
  invalidates the token it carries.
* `cookie.same_site` accepts what `Symfony\Component\HttpFoundation\Cookie` documents: `none`, `lax`
  or `strict` in any case, or an empty value to leave the attribute off the cookie. This is what
  makes it settable from an environment variable, which was rejected before whatever the variable
  held.
* `gesdinet:jwt:clear` reports how many tokens it revoked and lists them only with `-v`. Scripts
  parsing its output line by line should be checked.
* A refresh makes one query for the token rather than two. Nothing needs to change; a listener
  counting queries will see the difference.

## New configuration

All optional, all off or unlimited by default.

| Option | What it does |
| --- | --- |
| `hash_tokens.enabled` | Stores `sha256$` and the hash of the token instead of the token, so a copy of the database cannot be used to refresh |
| `max_tokens_per_user` | Limits how many refresh tokens a user may hold at once, revoking the least recently refreshed beyond it |
| `single_use_ttl_update` | On by default. Turned off, a token issued in place of a single use one inherits the expiry of the one it replaced |
| `refresh_token_manager` | Names a manager of your own, wiring none of the bundle's storage, so Doctrine need not be installed |
| `dbal_connection` | Stores tokens through a plain DBAL connection rather than the ORM or the ODM |
| `api_platform.enabled` | Documents the refresh endpoint, and the refresh token in the login response, in the OpenAPI specification API Platform generates |

### Turning on `hash_tokens`

Worth reading before enabling, since it changes what a stored token holds.

Existing tokens keep working: one stored before the change is looked up as it is and rewritten
hashed the first time it is used. Once they have all expired — a `ttl` after you turn this on — set
`accept_stored_in_the_clear: false`, so that a token read from an older backup stops working.

The consequence to check first: `getRefreshToken()` on a token read back from storage returns the
hash, because the hash is what is stored. The value the client is given exists only at the moment it
is issued. Anything of yours that reads a token back out of the database has to be looked at.

## Interfaces

Implementations of `RefreshTokenManagerInterface` are unaffected. It has not changed, and is now
held to the shared test suite by a manager written outside the bundle, so it stays implementable on
its own.

The additions are on separate interfaces, which an existing implementation may ignore:

* `Gesdinet\JWTRefreshTokenBundle\Model\ListRefreshTokenManagerInterface` — `findAllForUser()`,
  returning every token issued to a user, the one expiring last first. Expired ones are included.
* `Gesdinet\JWTRefreshTokenBundle\Model\RevokeRefreshTokenManagerInterface` — `revokeAllForUser()`
  and `revokeAllButNewestForUser()`.
* `Gesdinet\JWTRefreshTokenBundle\Doctrine\DeleteRefreshTokenRepositoryInterface` — `deleteByUser()`,
  `deleteToken()` and `deleteAllButNewestForUser()`.

Both manager interfaces are aliased to the manager for every backend, the DBAL one included, so they
can be injected by type.

`max_tokens_per_user` needs a manager implementing `RevokeRefreshTokenManagerInterface`. All three
of the bundle's own do. A manager of your own that does not is told so with a `LogicException`
rather than left appearing to enforce a limit it is not enforcing.

## Custom repositories

A repository implementing `DeleteRefreshTokenRepositoryInterface` from scratch gains
`deleteAllButNewestForUser()`. Repositories extending the bundle's own get it for free. This
interface was introduced during this cycle and has not appeared in a release, so nothing published
is affected.

## Constructor changes

`AttachRefreshTokenOnSuccessListener` takes two further optional arguments, the token limit and the
token storage. Both default to null and the service is configured by the bundle, so this only
matters to an application constructing the listener itself — which is no longer a way to change its
behaviour anyway, since the options it reads are configuration.
