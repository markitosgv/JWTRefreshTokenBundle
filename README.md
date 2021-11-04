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

This bundle requires PHP 7.4 or later and Symfony 3.4, 4.4, or 5.2+.

If you want to use this bundle with previous Symfony versions, please use 0.2.x releases.

**Protip:** Though the bundle doesn't force you to do so, it is highly recommended to use HTTPS.

## Installation

### Step 1: Download the Bundle

**It's important you manually require either Doctrine's ORM or MongoDB ODM as well, these packages are not required automatically as you can choose between them. Failing to do so may trigger errors on installation**

With Doctrine's ORM

```bash
composer require doctrine/orm doctrine/doctrine-bundle gesdinet/jwt-refresh-token-bundle
```

With Doctrine's MongoDB ODM

```bash
composer require doctrine/mongodb-odm-bundle gesdinet/jwt-refresh-token-bundle
```

or edit composer.json:

```json
{
  "require": {
    "doctrine/doctrine-bundle": "^1.12 || ^2.0",
    "doctrine/mongodb-odm-bundle": "^3.4 || ^4.0",
    "doctrine/orm": "^2.7",
    "gesdinet/jwt-refresh-token-bundle": "^1.0"
  }
}
```

Alternatively, a custom persistence layer can be used.

For that purpose:

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

#### Symfony Standard Application

For an application based on the Symfony Standard structure, you will need to add the bundle to your `AppKernel` class' `registerBundles()` method.

```php
<?php

use Symfony\Component\HttpKernel\Kernel;

class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = [
            // ...
            new Gesdinet\JWTRefreshTokenBundle\GesdinetJWTRefreshTokenBundle(),
        ];
    }
}
```

### Step 3 (Symfony 5.3+)

#### Define the refresh token route

Open your routing configuration file and add the following route to it:

```yaml
# app/config/routing.yml or config/routes.yaml
gesdinet_jwt_refresh_token:
    path: /api/token/refresh
# ...
```

#### Configure the authenticator

Add the below to your security configuration file:

```yaml
# app/config/security.yml or config/packages/security.yaml
security:
    enable_authenticator_manager: true

    firewalls:
        # put it before all your other firewall API entries
        api_token_refresh:
            pattern: ^/api/token/refresh
            stateless: true
            refresh_jwt: ~
    # ...

    access_control:
        # ...
        - { path: ^/api/token/refresh, roles: PUBLIC_ACCESS }
        # ...
# ...
```

### Step 3 (Symfony 5.2-)

#### Define the refresh token route

Open your routing configuration file and add the following route to it:

**Symfony 3 Version:**
```yaml
# app/config/routing.yml
gesdinet_jwt_refresh_token:
    path:     /api/token/refresh
    defaults: { _controller: gesdinet.jwtrefreshtoken:refresh }
# ...
```

**Symfony 4 Version:**
```yaml
# config/routes.yaml
gesdinet_jwt_refresh_token:
    path:       /api/token/refresh
    controller: gesdinet.jwtrefreshtoken::refresh
# ...
```

#### Configure the security firewall

Add the below to your security configuration file:

```yaml
# app/config/security.yml or config/packages/security.yaml
security:
    firewalls:
        # put it before all your other firewall API entries
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

### Step 4: Update your database schema

With the next commands you will add the table to store your refresh tokens to your database

```bash
php bin/console doctrine:schema:update --force

# or make and run a migration:
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

## Usage

The below configurations can be put in the following files, depending on the Symfony version you are running:

**Symfony 3 Version:** `app/config/config.yml`  
**Symfony 4 Version:** `config/packages/gesdinet_jwt_refresh_token.yaml`

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

### Set The User Provider

#### Symfony 5.3+

You can define a user provider to use for the authenticator its configuration:

```yaml
# app/config/security.yml or config/packages/security.yaml
security:
    firewalls:
        api_token_refresh:
            pattern: ^/api/token/refresh
            stateless: true
            refresh_jwt:
                provider: user_provider_service_id
```

By default, when a user provider is not specified, then the user provider for the firewall is used instead.

#### Symfony 5.2-

*NOTE* This setting is deprecated and is not used with the `refresh_jwt` authenticator

You can define your own user provider, by default the `gesdinet.jwtrefreshtoken.user_provider` service is used. You can change this value by adding this line to your config:

```yaml
gesdinet_jwt_refresh_token:
    user_provider: user_provider_service_id
```

For example, if you are using FOSUserBundle, `user_provider` must be set to `fos_user.user_provider.username_email`.

For Doctrine ORM UserProvider, `user_provider` must be set to `security.user.provider.concrete.<your_user_provider_name_in_security_yaml>`.

For example, in your `app/config/security.yml` or `config/packages/security.yaml`:
```yaml
security:
    # ...
    providers:
        app_user_provider:
            # ...
    firewalls:
    # ...
# ...
```

then your user_provider_service_id is `security.user.provider.concrete.app_user_provider`.

### Doctrine Manager Type

By default, this bundle sets the Doctrine Manager type to the ORM. If you want to use Doctrine's MongoDB ODM you have to change this value:

```yaml
gesdinet_jwt_refresh_token:
    manager_type: mongodb
```

### Set The User Checker

#### Symfony 5.3+

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

#### Symfony 5.2-

*NOTE* This setting is deprecated and is not used with the `refresh_jwt` authenticator

You can define your own user checker, by default the `security.user_checker` service is used. You can change this value by adding this line to your config:

```yaml
gesdinet_jwt_refresh_token:
    user_checker: user_checker_service_id
```

You will probably want to use a custom user provider along with your user checker to ensure that the checker receives the right type of user.

### Single Use Tokens

You can configure the refresh token so it can only be consumed _once_. If set to `true` and the refresh token is consumed, a new refresh token will be provided. 

To enable this behavior add this line to your config:

```yaml
gesdinet_jwt_refresh_token:
    single_use: true
```

### Configure refresh token in cookie

By default, the refresh token is returned in a JsonResponse. You can use the following configuration to set it in a HttpOnly cookie instead. The refresh token is automatically extracted from the cookie during refresh.

```yaml
gesdinet_jwt_refresh_token:
    cookie:
      enabled: true
      same_site: lax               # default value
      path: /                      # default value
      domain: null                 # default value
      http_only: true              # default value
      secure: true                 # default value
      remove_token_from_body: true # default value
```

### Use another entity for refresh tokens

You can define your own refresh token class on your project.

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

### Use another object manager

You can configure the bundle to use any object manager,  just add this line to your config.yml file:

```yaml
gesdinet_jwt_refresh_token:
    object_manager: my.specific.entity_manager.id
```

### Disable automatic Doctrine mappings

*NOTE* This setting is deprecated and is no longer used

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

We give you two commands to manage tokens.

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

If you want to do something when token is refreshed you can listen for `gesdinet.refresh_token` event.

For example:

```php
<?php

namespace App\EventListener;

use Gesdinet\JWTRefreshTokenBundle\Event\RefreshEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LogListener implements EventSubscriberInterface
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function log(RefreshEvent $event)
    {
        $refreshToken = $event->getRefreshToken()->getRefreshToken();
        $user = $event->getToken()->getUser()->getUsername();
        
        $this->logger->debug(sprintf('User "%s" has refreshed it\'s JWT token with refresh token "%s".', $user, $refreshToken));
    }
    
    public static function getSubscribedEvents()
    {
        return array(
            'gesdinet.refresh_token' => 'log',
        );
    }
}
```

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
