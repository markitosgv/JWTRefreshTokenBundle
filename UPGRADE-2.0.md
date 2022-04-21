# Upgrade from 1.x to 2.0

The below guide will assist in upgrading from the 1.x versions to 2.0.

## Bundle Requirements

- Symfony 5.4, 6.4, or 7.2+
- PHP 8.2 or later

## Removed Features

- Removed the `AbstractRefreshToken` classes from the `Gesdinet\JWTRefreshTokenBundle\Document` and `Gesdinet\JWTRefreshTokenBundle\Entity` namespaces, use the `RefreshToken` class from the same namespace instead
