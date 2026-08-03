# Upgrade from 2.0 to 2.1

Nothing has to be changed in an application using the bundle through its configuration and its
services. The points below only matter when a class of the bundle is implemented or its return
values are relied upon.

## Bundle Requirements

Unchanged: Symfony 6.4, 7.2+ or 8.0, and PHP 8.2 or later.

The `php` constraint is now written as `^8.2` rather than `>=8.2`. The minimum is the same, but the
range no longer claims support for a future PHP 9.

## Custom repositories

`Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenRepositoryInterface` documents, through a
`@method` tag, that `findOneBy()` takes an optional `$orderBy` argument. The bundle calls it with
that argument to retrieve the last token of a user.

The signature is not declared on the interface, so nothing breaks. A repository extending
`Doctrine\ORM\EntityRepository` or `Doctrine\ODM\MongoDB\Repository\DocumentRepository` already
accepts it. A repository implementing the interface from scratch has to accept it too:

```php
public function findOneBy(array $criteria, ?array $orderBy = null): ?object
```

## Changed return values

These were fixed to match what the interfaces already documented. An application reading them may
observe the difference:

* `RefreshTokenManagerInterface::revokeAllInvalidBatch()` returns every revoked token. It used to
  return the last batch read, which is empty by the time the loop ends, so it always returned an
  empty array. The `gesdinet:jwt:clear` command consequently reported that there was nothing to
  revoke even after deleting tokens.
* `RefreshTokenManagerInterface::delete()` returns `0` when the token is not in storage. With the
  ODM it used to return `1` regardless.
* `AuthenticationSuccessHandler::onAuthenticationSuccess()` is typed `?Response`. It returns
  whatever the decorated handler returns, and
  `Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface` allows that
  to be null. What is returned at runtime has not changed.

## Stricter validation

Values that used to fail with a `TypeError`, or not fail at all, are now rejected where they are
read:

* `refresh_token_class` reports a configuration error when the class cannot be loaded, instead of
  raising a `TypeError` while building the container.
* `gesdinet:jwt:clear` exits with `Command::INVALID` when `--batch-size` is not a positive number. A
  batch of zero read no tokens, so the command reported success while leaving every expired token in
  place.
* A refresh token without a username is rejected with an `InvalidTokenException` instead of failing
  with a `TypeError` while building the passport.
* `PostRefreshTokenAuthenticationToken` throws an `UnexpectedValueException` when the serialized
  state it is given does not hold a refresh token.

## Fixed with the MongoDB ODM

* The document repository reads its results through `Query::getIterator()`. Passing the result of
  `Query::execute()` around meant `revokeAllInvalid()` handed back something that was not an array.
* `revokeAllInvalidBatch()` no longer loops forever. Its condition tested the repository result with
  `empty()`, which is never true for the iterator the ODM returns.
* Expired tokens are no longer skipped. Each batch is deleted before the next is read, so the
  remaining tokens shift down and the offset must stay where it is.
