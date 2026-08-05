# Upgrading with Rector

Rule sets for every hop from 1.5 to 3.0, and the order to run them in.

**Read this first: most of the work is not code.** What changed across these releases is mostly
configuration, the database schema, and which classes may be extended. Rector reads PHP, so it
handles the class and container-id renames — real work, and tedious to do by hand across a large
application — and nothing else. Each set says what it deliberately leaves to you and why. A set that
looks like it handled the upgrade when it did not is worse than one that admits it.

So: run the set, then work the checklist for that hop. The checklist is the upgrade.

## Setting up

Rector is not a dependency of this bundle. Add it to your application:

```bash
composer require --dev rector/rector
```

The sets live in this package, so they are on disk once the bundle is installed:

```php
// rector.php
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/src'])
    ->withSets([
        __DIR__.'/vendor/gesdinet/jwt-refresh-token-bundle/rector/sets/gesdinet-jwt-refresh-token-20.php',
    ]);
```

Always look before you leap:

```bash
vendor/bin/rector process --dry-run
```

## The order

**One hop at a time, running your tests in between.** Every hop is a version you can stop at, and
finding out which one broke something is much easier than finding out which of four did.

Each step is: bump the constraint, install, run the set, run the checklist, run your tests, commit.

| Step | Set | What it rewrites |
| --- | --- | --- |
| 1.5 → 2.0 | `gesdinet-jwt-refresh-token-20.php` | Two class renames and every container id |
| 2.0 → 2.1 | `gesdinet-jwt-refresh-token-21.php` | Nothing — 2.1 fixed return values |
| 2.1 → 2.2 | `gesdinet-jwt-refresh-token-22.php` | Nothing — 2.2 only added |
| 2.2 → 3.0 | `gesdinet-jwt-refresh-token-30.php` | Nothing — 3.0 changed config and schema |

There is also `gesdinet-jwt-refresh-token-all.php`, which imports all four in order. Use it when the
application is small enough that the whole jump is one piece of work; you lose the chance to run your
tests between versions, which is the main reason not to.

**Stopping at 2.2 is a legitimate destination.** 3.0 needs Symfony 8 and PHP 8.4; 2.2 supports
Symfony 6.4 and 7.x, which are maintained until November 2028. If you are not on Symfony 8, do the
first three hops and stop.

---

## Step 1: 1.5 → 2.0

```bash
composer require gesdinet/jwt-refresh-token-bundle:^2.0
vendor/bin/rector process --dry-run   # then without --dry-run
```

### What the set does

* `Entity\AbstractRefreshToken` and `Document\AbstractRefreshToken` → `RefreshToken` in the same
  namespace.
* Container ids from `gesdinet.jwtrefreshtoken.*` to `gesdinet_jwt_refresh_token.*`, wherever they
  appear as strings in PHP.
* Ids of services that were **removed** are renamed onto ids that do not exist, so you get "service
  not found" at the line that wants one, rather than a string that quietly resolves elsewhere.

### What it leaves you

**1. The controller has to go, and the firewall replaces it.** This is the big one. 1.x refreshed
through a controller calling the `gesdinet.jwtrefreshtoken` service; 2.0 does it with an
authenticator, before any controller is reached.

```yaml
# config/routes.yaml — the route keeps its path and loses its controller
api_refresh_token:
    path: /api/token/refresh

# config/packages/security.yaml
security:
    firewalls:
        api:
            refresh_jwt:
                check_path: /api/token/refresh   # or api_refresh_token
```

Leaving the controller in place loads a class Symfony deleted and fails with
`Attempted to load class "AbstractGuardAuthenticator"`. Removing it without configuring the
authenticator leaves the path routable and unanswered, and Symfony reports the route as having no
controller — a message that names your route while your route is fine.

**2. `refresh_token_class` is now required**, and is checked to implement `RefreshTokenInterface`.

**3. Configuration nodes that are gone.** Rector does not read YAML, so these are yours to delete:

| Removed | What to do |
| --- | --- |
| `refresh_token_entity` | Rename to `refresh_token_class` |
| `entity_manager` | Rename to `object_manager` |
| `user_provider`, `user_checker` | Set them on the security firewall instead |
| `logout_firewall` | `invalidate_token_on_logout` on the `refresh_jwt` authenticator |
| `firewall`, `user_identity_field`, `doctrine_mappings`, `manager_type` | Delete; no replacement |

**4. Classes that are gone with no replacement**, which the set renames to nothing on purpose:
`Service\RefreshToken`, `Security\Provider\RefreshTokenProvider`, the Guard
`Security\Authenticator\RefreshTokenAuthenticator`, and `Model\RefreshTokenManager` — implement
`Model\RefreshTokenManagerInterface` directly instead of extending it.

**5. A custom repository gains `findInvalidBatch()`**, and a custom manager `revokeAllInvalidBatch()`.

---

## Step 2: 2.0 → 2.1

```bash
composer require gesdinet/jwt-refresh-token-bundle:^2.1
```

The set is empty. 2.1 fixed methods that were returning the wrong thing, and there is no way to
rewrite code that was reading a wrong answer into code that reads a right one.

### What to check

* **`revokeAllInvalidBatch()` returns every revoked token.** It used to return the last batch read,
  which is empty by the time the loop ends — so it always returned `[]`. Code treating that as
  "nothing was revoked" was reading a bug as an answer, and now gets the truth.
* **`delete()` returns `0`** for a token that was not stored. With the ODM it used to return `1`
  either way, so anything branching on it behaved differently there.
* **A repository written from scratch** against `RefreshTokenRepositoryInterface` has to accept
  `findOneBy(array $criteria, ?array $orderBy = null)`. Extending Doctrine's own is already fine.
* `gesdinet:jwt:clear` now exits non-zero on a `--batch-size` that is not positive. A batch of zero
  read no tokens, so it used to report success while leaving every expired token in place — check
  any script that passed one.

---

## Step 3: 2.1 → 2.2

```bash
composer require gesdinet/jwt-refresh-token-bundle:^2.2
```

The set is empty for a happier reason: 2.2 only added. Every new capability arrived on its own
interface so that existing implementations could ignore it, and every new option is off by default.

### What to check

* **`cookie.same_site`** no longer accepts values `Symfony\Component\HttpFoundation\Cookie` does not
  document. If yours was one of those, the container refuses to build and tells you.
* **The refresh cookie now expires with the token inside it**, rather than a `ttl` from the moment it
  was set. If you were relying on the old, longer window, this shortens it.
* **`gesdinet:jwt:clear` lists tokens only with `-v`** and reports a count otherwise. A script
  parsing its output needs a look.
* **An expired JWT can now be exchanged.** If you split the refresh endpoint onto its own firewall to
  work around that, you can put it back — see UPGRADE-2.2.md.

### Worth turning on while you are here

`hash_tokens`, so a copy of the database cannot be used to refresh, and `max_tokens_per_user`. Both
are opt-in and neither needs a migration.

---

## Step 4: 2.2 → 3.0

```bash
composer require gesdinet/jwt-refresh-token-bundle:^3.0
```

Check you are on Symfony 8 and PHP 8.4 first; if not, stop at 2.2, which is supported.

The set is empty. A major release usually renames things and this one deliberately did not: what
changed is configuration, the schema, and which classes may be extended.

### What to check, in order

**1. Run the migration first.** The token classes gained `family` and `family_valid`. Doctrine reads
every mapped field, so until the columns exist the first query fails:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

**2. `check_path` is now required** on every `refresh_jwt`. It used to default to `/login_check`,
which is Lexik's login path and never right for refreshing.

**3. `dbal_columns`, if you configure it, has to name `id`.**

**4. Nine classes are now `final`.** Catching the exceptions is unaffected; only extending them is.
`AbstractRefreshToken`, the `RefreshToken` classes and the repositories are all still extendable —
bringing your own is the documented path.

**5. `RefreshEvent` takes the request.** Only code constructing the event is affected, and the bundle
is what dispatches it, so this is unusual. A missing argument is an `ArgumentCountError` with a file
and a line.

**6. DBAL 3 is dropped**, and DBAL index names now include the table name. Existing tables are left
alone.

### Worth turning on while you are here

All opt-in, all documented in UPGRADE-3.0.md: `reuse_detection` (the one with the most security
value), `block_jwts_on_revocation`, `rate_limiter`, `max_session_lifetime`, and the per-firewall
options if you have more than one API.

---

## When Rector rewrites something you did not expect

Run it on a clean tree so `git diff` is the whole story, and read the diff before committing. The
string renames in particular match on value: if your application has a service of its own genuinely
named `gesdinet.jwtrefreshtoken.something`, it will be renamed too. That is what `--dry-run` is for.
