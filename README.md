JWTRefreshTokenBundle
=====================

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/gesdinet/JWTRefreshTokenBundle/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/gesdinet/JWTRefreshTokenBundle/?branch=master)
[![Run Tests](https://github.com/markitosgv/JWTRefreshTokenBundle/workflows/Run%20Tests/badge.svg?branch=master)](https://github.com/markitosgv/JWTRefreshTokenBundle/actions)
[![Code Coverage](https://scrutinizer-ci.com/g/gesdinet/JWTRefreshTokenBundle/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/gesdinet/JWTRefreshTokenBundle/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/gesdinet/jwt-refresh-token-bundle/v/stable)](https://packagist.org/packages/gesdinet/jwt-refresh-token-bundle)
[![Total Downloads](https://poser.pugx.org/gesdinet/jwt-refresh-token-bundle/downloads)](https://packagist.org/packages/gesdinet/jwt-refresh-token-bundle)
[![License](https://poser.pugx.org/gesdinet/jwt-refresh-token-bundle/license)](https://packagist.org/packages/gesdinet/jwt-refresh-token-bundle)
[![StyleCI](https://styleci.io/repos/42582199/shield)](https://styleci.io/repos/42582199)

The purpose of this bundle is manage refresh tokens with JWT (Json Web Tokens) in an easy way. This bundles uses [LexikJWTAuthenticationBundle](https://github.com/lexik/LexikJWTAuthenticationBundle). Supports Doctrine ORM/ODM.

## Prerequisites

This bundle requires PHP 8.1 or later and Symfony 5.4, or 6.3+.

For support with older Symfony versions, please use the 1.x release.

**Protip:** Though the bundle doesn't force you to do so, it is highly recommended to use HTTPS.

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

Or, manually edit your project's `composer.json` file to add the required packages:

```json
{
  "require": {
    "doctrine/doctrine-bundle": "^2.10",
    "doctrine/mongodb-odm": "^2.3",
    "doctrine/mongodb-odm-bundle": "^4.5",
    "doctrine/orm": "^2.12",
    "gesdinet/jwt-refresh-token-bundle": "^2.0"
  }
}
```

Alternatively, a custom persistence layer can be used.

For that purpose, you must:

* provide an implementation of `Doctrine\Persistence\ObjectManager`
* configure the bundle to [use your object manager](#use-another-object-manager)

### Step 2: Enable the Bundle

#### Symfony Flex Application

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

If you are using the Doctrine ORM, the below contents should be placed at `src/Entity/RefreshToken.php` (use annotations OR attributes):
 
```php
<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;

/**
 * @ORM\Entity
 * @ORM\Table("refresh_tokens")
 */
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

/**
 * @ODM\Document(collection="refresh_tokens")
 */
class RefreshToken extends BaseRefreshToken
{
}
```

### Step 4

#### Define the refresh token route

Open your routing configuration file and add the following route to it:

```yaml
# config/routes.yaml
api_refresh_token:
    path: /api/token/refresh
# ...
```

#### Configure the authenticator

To enable the authenticator, you should add it to your API firewall(s) alongside the `json_login` and `jwt` authenticators.

The complete firewall configuration should look similar to the following:

```yaml
# config/packages/security.yaml
security:
    # this config is only required on Symfony 5.4, you can leave it out on Symfony 6
    enable_authenticator_manager: true

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
    # ...

    access_control:
        # ...
        - { path: ^/api/(login|token/refresh), roles: PUBLIC_ACCESS }
        # ...
# ...
```

### Step 5: Update your database schema

You will need to add the table for the refresh tokens to your application's database.

With migrations:

```
# If using the MakerBundle:
php bin/console make:migration
# Without the MakerBundle:
php bin/console doctrine:migrations:diff

php bin/console doctrine:migrations:migrate
```

Without migrations (**NOT RECOMMENDED**):

```bash
php bin/console doctrine:schema:update --force
```

## Usage

The below options can be configured through the bundle's configuration in the `config/packages/gesdinet_jwt_refresh_token.yaml` file (make sure to create it if it does not already exist).

### Token TTL

You can define the refresh token TTL, this value is set in seconds and defaults to 1 month. You can change this value adding this line to your config:

```yaml
gesdinet_jwt_refresh_token:
    ttl: 2592000
```

### Update Token TTL

You can configure the bundle to refresh the TTL on a refresh token when it is used, by default this feature is disabled. You can change this value adding this line to your config:

```yaml
gesdinet_jwt_refresh_token:
    ttl_update: true
```

### Config Firewall Name

*NOTE* This setting is deprecated and is not used with the `refresh_jwt` authenticator

You can define Firewall name. Default value is `api`. You can change this value adding this line to your config:

```yaml
gesdinet_jwt_refresh_token:
    firewall: api
```

### Refresh Token Parameter Name

You can define the parameter name for the refresh token when it is read from the request, the default value is `refresh_token`. You can change this value adding this line to your config:

```yaml
gesdinet_jwt_refresh_token:
    token_parameter_name: refreshToken
```

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

### Set The User Provider

You can define a user provider to use for the authenticator its configuration.

Note, if your application has multiple user providers, you **MUST** configure this value for either the firewall or the provider.

```yaml
# app/config/security.yml or config/packages/security.yaml
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
# app/config/security.yml or config/packages/security.yaml
security:
    firewalls:
        api_token_refresh:
            pattern: ^/api/token/refresh
            stateless: true
            user_checker: user_checker_service_id
            refresh_jwt: ~
```

### Single Use Tokens

You can configure the refresh token so it can only be consumed _once_. If set to `true` and the refresh token is consumed, a new refresh token will be provided. 

To enable this behavior add this line to your config:

```yaml
gesdinet_jwt_refresh_token:
    single_use: true
```

### Set the refresh token in a cookie

By default, the refresh token is returned in the body of a JSON response. You can use the following configuration to set it in a HttpOnly cookie instead. The refresh token is automatically extracted from the cookie during refresh.

To allow users to logout when using cookies, you need to [configure the `LogoutEvent` to trigger on a specific route](#invalidate-refresh-token-on-logout), and call that route during logout.

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
# in security.yaml
security:
    firewalls:
        api:
            logout:
                path: api_token_invalidate
```
```yaml
# in routes.yaml
api_token_invalidate:
    path: /api/token/invalidate
```

If you want to configure the `LogoutEvent` to trigger on a different firewall, the name of the firewall has to be configured:

```yaml
# in security.yaml
security:
    firewalls:
        myfirewall:
            logout:
                path: api_token_invalidate
```
```yaml
# in routes.yaml
api_token_invalidate:
    path: /api/token/invalidate
```
```yaml
# in gesdinet_jwt_refresh_token.yaml
gesdinet_jwt_refresh_token:
    logout_firewall: myfirewall
```

### Doctrine Manager Type

By default, the bundle will try to set the appropriate Doctrine object manager for your application using the following logic to define the manager type:

- If the `manager_type` configuration key is set to "mongodb", the MongoDB ODM is used
- If the `manager_type` configuration key is set to "orm" (default), and the ORM is not installed but the MongoDB ODM is installed, the MongoDB ODM is used
- By default, the `manager_type` is "orm" and the ORM is used

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
 *
 * @ORM\Table("jwt_refresh_token")
 */
class JwtRefreshToken extends RefreshToken
{
}
```

When using the Doctrine MongoDB ODM, create a class extending `Gesdinet\JWTRefreshTokenBundle\Document\RefreshToken` in your application:

```php
<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Gesdinet\JWTRefreshTokenBundle\Document\RefreshToken;

/**
 * This class extends Gesdinet\JWTRefreshTokenBundle\Document\RefreshToken to have another collection name.
 *
 * @MongoDB\Document(collection="jwt_refresh_token")
 */
class JwtRefreshToken extends RefreshToken
{
}
```
Then declare this class adding this line to your config.yml file:

```yaml
gesdinet_jwt_refresh_token:
    refresh_token_class: App\Entity\JwtRefreshToken
```

*NOTE* If using another object manager, it is recommended your object class extends from `Gesdinet\JWTRefreshTokenBundle\Model\AbstractRefreshToken` which implements all required methods from `Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface`.

### Disable automatic Doctrine mappings

*NOTE*: This setting is deprecated and is no longer used

On some occasions, you may not want to have default Doctrine mappings of object manager enabled as you use neither ORM nor ODM but i.e. using DoctrineBundle for DBAL.

To disable dynamic Doctrine mapping add this line to your config:

```yaml
gesdinet_jwt_refresh_token:
    doctrine_mappings: false
```

### Generating Tokens

When you authenticate through /api/login_check with user/password credentials, LexikJWTAuthenticationBundle now returns a JWT Token and a Refresh Token data.

```json
{
  "token": "eyxxxGciOiJSUzI1NiIsInR5cCI6IkpXUyJ9.eyJleHAiOjE0NDI0MDM3NTgsImVtYWlsIjoid2VibWFzdGVyQGdlc2RpbmV0LmNvbSIsImlhdCI6IjE0NDI0MDM3MzgifQ.bo5pre_v0moCXVOZOj-s85gVnBLzdSdsltPn3XrkmJaE8eaBo_zcU2pnjs4dUc9hhwNZK8PL6SmSNcQuTUj4OMK7sUDfXr62a05Ds-UgQP8B2Kpc-ZOmSts_vhgo6xJNCy8Oub9-pRA_78WzUUxt294w0IArrNlgQAGewk65RSMThOif9G6L7HzBM4ajFZ-kMDypz2zVQea1kry-m-XXKNDbERCSHnMeV3rANN48SX645_WEvwaHy0agChR4hTnThzLof2bShA7j7HmnSPpODxQszS5ZBHdMgTvYhlcWJmwYswCWCTPl3lsqVq_UOFI5_4arpSNlUwZsichqxXVAHX5idZqCWtoaqAbvNQe2IpinYajoXw-MlYKvcN2TLUF_8sy529olLUagf4FCpCO6JFxovv0E7ll9tUOVvx9LlannqV8976q5XCOoXszKonZSH7DhsBlW5Emjv7PailbARZ-hfl4YlamyY2QbnxAswYycfoxqJxbbIKYGA8dlebdvMyC7m9VATnasTuKeEKS3mP5iyDgWALBHNYXm1FM-12zHBdN3PbOgxmy_OBGvk05thYFEf2WVmyedtFHy4TGlI0-otUTAf2swQAXWhKtkLWzokWWF7l5iNzam1kkEgql5EOztXHDZpmdKVHWBVNvN3J5ivPjjJBm6sGusf-radcw",
  "refresh_token": "xxx00a7a9e970f9bbe076e05743e00648908c38366c551a8cdf524ba424fc3e520988f6320a54989bbe85931ffe1bfcc63e33fd8b45d58564039943bfbd8dxxx"
}
```

The refresh token is persisted as a `RefreshTokenInterface` object. After that, when your JWT valid token expires, if you want to get a new one you can proceed in two ways:

- Send you user credentials again to /api/login_check. This generates another JWT with another Refresh Token.
- Ask to renew valid JWT with our refresh token. Make a POST call to /api/token/refresh url with refresh token as payload. In this way, you can always get a valid JWT without asking for user credentials. But **you must check** if the refresh token is still valid. Your refresh token will not change but its TTL will increase.

***Note that when a refresh token is consumed and the config option `single_use` is set to `true` the token will no longer be valid.***

```bash
curl -X POST -d refresh_token="xxxx4b54b0076d2fcc5a51a6e60c0fb83b0bc90b47e2c886accb70850795fb311973c9d101fa0111f12eec739db063ec09d7dd79331e3148f5fc6e9cb362xxxx" 'http://xxxx/token/refresh'
```

This call returns a new valid JWT token renewing valid datetime of your refresh token.

## Useful Commands

### Revoke all invalid tokens

If you want to revoke all invalid (datetime expired) refresh tokens you can execute:

```bash
php bin/console gesdinet:jwt:clear
```

The command optionally accepts a date argument which will delete all tokens older than the given time. This can be any value that can be parsed by the `DateTime` class.

```bash
php bin/console gesdinet:jwt:clear 2015-08-08
```

We recommend executing this command as a cronjob to remove invalid refresh tokens on an interval.

### Revoke a token

If you want to revoke a single token you can use this command:

```bash
php bin/console gesdinet:jwt:revoke TOKEN
```

## Events

### Token Refreshed

When a token is refreshed, the `gesdinet.refresh_token` event is dispatched with a `Gesdinet\JWTRefreshTokenBundle\Event\RefreshEvent` object.

### Refresh Token Failure

*NOTE* This event is only available when using the `refresh_jwt` authenticator with Symfony 5.4+.

When there is a failure authenticating the refresh token, the `gesdinet.refresh_token_failure` event is dispatched with a `Gesdinet\JWTRefreshTokenBundle\Event\RefreshAuthenticationFailureEvent` object.

### Refresh Token Not Found

*NOTE* This event is only available when using the `refresh_jwt` authenticator with Symfony 5.4+.

When there is a failure authenticating the refresh token, the `gesdinet.refresh_token_not_found` event is dispatched with a `Gesdinet\JWTRefreshTokenBundle\Event\RefreshTokenNotFoundEvent` object.

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

### Prioritizing Extractors

The `gesdinet_jwt_refresh_token.request_extractor` container tag supports prioritizing extractors, you can use this to set the preferred order for your extractors by adding a `priority` attribute. The higher the number, the sooner the extractor will be run.

```yaml
services:
    App\Request\Extractor\HeaderExtractor:
        tags:
            - { name: gesdinet_jwt_refresh_token.request_extractor, priority: 25 }
```
