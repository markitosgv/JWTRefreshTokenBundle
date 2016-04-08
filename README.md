JWTRefreshTokenBundle
=====================

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/gesdinet/JWTRefreshTokenBundle/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/gesdinet/JWTRefreshTokenBundle/?branch=master)
[![Build Status](https://travis-ci.org/gesdinet/JWTRefreshTokenBundle.svg?branch=master)](https://travis-ci.org/gesdinet/JWTRefreshTokenBundle)
[![Code Coverage](https://scrutinizer-ci.com/g/gesdinet/JWTRefreshTokenBundle/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/gesdinet/JWTRefreshTokenBundle/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/gesdinet/jwt-refresh-token-bundle/v/stable)](https://packagist.org/packages/gesdinet/jwt-refresh-token-bundle)
[![Total Downloads](https://poser.pugx.org/gesdinet/jwt-refresh-token-bundle/downloads)](https://packagist.org/packages/gesdinet/jwt-refresh-token-bundle)
[![License](https://poser.pugx.org/gesdinet/jwt-refresh-token-bundle/license)](https://packagist.org/packages/gesdinet/jwt-refresh-token-bundle)
[![StyleCI](https://styleci.io/repos/42582199/shield)](https://styleci.io/repos/42582199)

The purpose of this bundle is manage refresh tokens with JWT (Json Web Tokens) in an easy way. This bundles uses [LexikJWTAuthenticationBundle](https://github.com/lexik/LexikJWTAuthenticationBundle). At the moment only supports Doctrine ORM.

Prerequisites
-------------

This bundle requires Symfony 2.5+ and supports Symfony 3 too.

**Protip:** Though the bundle doesn't enforce you to do so, it is highly recommended to use HTTPS.

Installation
------------

### Step 1: Download the Bundle

Add [`gesdinet/jwt-refresh-token-bundle`](https://packagist.org/packages/gesdinet/jwt-refresh-token-bundle) to your `composer.json` file:

```bash
$ composer require "gesdinet/jwt-refresh-token-bundle"
```

or edit composer.json:

    // ...
    "gesdinet/jwt-refresh-token-bundle": "~0.1",
    // ...

### Step 2: Enable the Bundle

Then, enable the bundle by adding the following line in the `app/AppKernel.php` file of your Symfony application:

```php
<?php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            // ...
            new Gesdinet\JWTRefreshTokenBundle\GesdinetJWTRefreshTokenBundle(),
        );
    }

    // ...
}
```

### Step 3: Configure your own routing to refresh token

Open your main routing configuration file (usually `app/config/routing.yml`) and copy the following four lines at the very beginning of it.

```yaml
# app/config/routing.yml
gesdinet_jwt_refresh_token:
    path:     /api/token/refresh
    defaults: { _controller: gesdinet.jwtrefreshtoken:refresh }
# ...
```

### Step 4: Allow anonymous access to refresh token

Add next lines on security.yml file:

```yaml
# app/config/security.yml
    firewalls:
        refresh:
            pattern:  ^/api/token/refresh
            stateless: true
            anonymous: true
    # ...

    access_control:
        # ...
        - { path: ^/api/token/refresh, roles: IS_AUTHENTICATED_ANONYMOUSLY }
        # ...
# ...
```

### Step 5: Update your schema

With the next command you will create a new table to handle your refresh tokens

```bash
php app/console doctrine:schema:update --force
```

USAGE
-----

### Config TTL

You can define Refresh Token TTL. Default value is 1 month. You can change this value adding this line to your config.yml file:

```yaml
gesdinet_jwt_refresh_token:
    ttl: 2592000
```

### Config TTL update

You can expand Refresh Token TTL on refresh. Default value is false. You can change this value adding this line to your config.yml file:

```yaml
gesdinet_jwt_refresh_token:
    ttl_update: true
```

This will reset the token TTL each time you ask a refresh.

### Config Firewall Name

You can define Firewall name. Default value is api. You can change this value adding this line to your config.yml file:

```yaml
gesdinet_jwt_refresh_token:
    firewall: api
```

### Config UserProvider

You can define your own UserProvider. By default we use our custom UserProvider. You can change this value by adding this line to your config.yml file:

```yaml
gesdinet_jwt_refresh_token:
    user_provider: user_provider_service_id
```

For example, if you are using FOSUserBundle, user_provider_service_id must be set to `fos_user.user_provider.username_email`.

### Use another entity for refresh tokens

You can define your own entity for refresh tokens.
Create the entity class extending `Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken` in you own bundle:

```php
namespace MyBundle;

use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Doctrine\ORM\Mapping as ORM;

/**
 * This class override Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken to have another table name.
 *
 * @ORM\Table("jwt_refresh_token")
 * @ORM\Entity(repositoryClass="Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository")
 * @UniqueEntity("refreshToken")
 */
class JwtRefreshToken extends BaseRefreshToken
{
}
```

Then declare this entity adding this line to your config.yml file:

```yaml
gesdinet_jwt_refresh_token:
    refresh_token_entity: MyBundle\JwtRefreshToken
```

### Use another entity manager

You can tell JWTRefreshTokenBundle to use another entity manager than default one (doctrine.orm.entity_manager).

Just add this line to your config.yml file:

```yaml
gesdinet_jwt_refresh_token:
    entity_manager: my.specific.entity_manager.id
```

### Generating Tokens

When you authenticate through /api/login_check with user/password credentials, LexikJWTAuthenticationBundle now returns a JWT Token and a Refresh Token data.

```json
{
  "token": "eyxxxGciOiJSUzI1NiIsInR5cCI6IkpXUyJ9.eyJleHAiOjE0NDI0MDM3NTgsImVtYWlsIjoid2VibWFzdGVyQGdlc2RpbmV0LmNvbSIsImlhdCI6IjE0NDI0MDM3MzgifQ.bo5pre_v0moCXVOZOj-s85gVnBLzdSdsltPn3XrkmJaE8eaBo_zcU2pnjs4dUc9hhwNZK8PL6SmSNcQuTUj4OMK7sUDfXr62a05Ds-UgQP8B2Kpc-ZOmSts_vhgo6xJNCy8Oub9-pRA_78WzUUxt294w0IArrNlgQAGewk65RSMThOif9G6L7HzBM4ajFZ-kMDypz2zVQea1kry-m-XXKNDbERCSHnMeV3rANN48SX645_WEvwaHy0agChR4hTnThzLof2bShA7j7HmnSPpODxQszS5ZBHdMgTvYhlcWJmwYswCWCTPl3lsqVq_UOFI5_4arpSNlUwZsichqxXVAHX5idZqCWtoaqAbvNQe2IpinYajoXw-MlYKvcN2TLUF_8sy529olLUagf4FCpCO6JFxovv0E7ll9tUOVvx9LlannqV8976q5XCOoXszKonZSH7DhsBlW5Emjv7PailbARZ-hfl4YlamyY2QbnxAswYycfoxqJxbbIKYGA8dlebdvMyC7m9VATnasTuKeEKS3mP5iyDgWALBHNYXm1FM-12zHBdN3PbOgxmy_OBGvk05thYFEf2WVmyedtFHy4TGlI0-otUTAf2swQAXWhKtkLWzokWWF7l5iNzam1kkEgql5EOztXHDZpmdKVHWBVNvN3J5ivPjjJBm6sGusf-radcw",
  "refresh_token": "xxx00a7a9e970f9bbe076e05743e00648908c38366c551a8cdf524ba424fc3e520988f6320a54989bbe85931ffe1bfcc63e33fd8b45d58564039943bfbd8dxxx"
}
```

This refresh token is persisted in RefreshToken entity. After that, when your JWT valid token expires, if you want to get a new one you can proceed in two ways:

- Send you user credentials again to /api/login_check. This generates another JWT with another Refresh Token.

- Ask to renew valid JWT with our refresh token. Make a POST call to /api/token/refresh url with refresh token as payload. In this way, you can always get a valid JWT without asking for user credentials. But **you must notice** if refresh token is still valid. Your refresh token do not change but valid datetime will increase.

```bash
curl -X POST -d refresh_token="xxxx4b54b0076d2fcc5a51a6e60c0fb83b0bc90b47e2c886accb70850795fb311973c9d101fa0111f12eec739db063ec09d7dd79331e3148f5fc6e9cb362xxxx" 'http://xxxx/token/refresh'
```

This call returns a new valid JWT token renewing valid datetime of your refresh token.

Useful Commands
---------------

We give you two commands to manage tokens.

### Revoke all invalid tokens

If you want to revoke all invalid (datetime expired) refresh tokens you can execute:

```bash
php app/console gesdinet:jwt:clear
```

Optional argument is datetime, it deletes all tokens smaller than this datetime:

```bash
php app/console gesdinet:jwt:clear 2015-08-08
```

We recommend to execute this command with a cronjob to remove invalid refresh tokens every certain time.

### Revoke a token

If you want to revoke a single token you can use this:

```bash
php app/console gesdinet:jwt:revoke TOKEN
```
