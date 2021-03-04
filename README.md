JWTRefreshTokenBundle
=====================

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/gesdinet/JWTRefreshTokenBundle/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/gesdinet/JWTRefreshTokenBundle/?branch=master)
[![Build Status](https://travis-ci.org/markitosgv/JWTRefreshTokenBundle.svg?branch=master)](https://travis-ci.org/markitosgv/JWTRefreshTokenBundle)
[![Code Coverage](https://scrutinizer-ci.com/g/gesdinet/JWTRefreshTokenBundle/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/gesdinet/JWTRefreshTokenBundle/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/gesdinet/jwt-refresh-token-bundle/v/stable)](https://packagist.org/packages/gesdinet/jwt-refresh-token-bundle)
[![Total Downloads](https://poser.pugx.org/gesdinet/jwt-refresh-token-bundle/downloads)](https://packagist.org/packages/gesdinet/jwt-refresh-token-bundle)
[![License](https://poser.pugx.org/gesdinet/jwt-refresh-token-bundle/license)](https://packagist.org/packages/gesdinet/jwt-refresh-token-bundle)
[![StyleCI](https://styleci.io/repos/42582199/shield)](https://styleci.io/repos/42582199)

The purpose of this bundle is manage refresh tokens with JWT (Json Web Tokens) in an easy way. This bundles uses [LexikJWTAuthenticationBundle](https://github.com/lexik/LexikJWTAuthenticationBundle). Supports Doctrine ORM/ODM.

Prerequisites
-------------

This bundle requires Symfony 3.4+, 4.0+ or 5.0+.

If you want to use this bundle with previous Symfony versions, please use 0.2.x releases.

**Protip:** Though the bundle doesn't enforce you to do so, it is highly recommended to use HTTPS.

Installation
------------

### Step 1: Download the Bundle

**It's important you manually require either Doctrine's ORM or MongoDB ODM as well, these packages are not required automatically now as you can choose between them. Failing to do so may trigger errors on installation**

With Doctrine's ORM

```bash
$ composer require "doctrine/orm" "doctrine/doctrine-bundle" "gesdinet/jwt-refresh-token-bundle"
```

With Doctrine's MongoDB ODM

```bash
$ composer require "doctrine/mongodb-odm-bundle" "gesdinet/jwt-refresh-token-bundle"
```

or edit composer.json:

    // ...
    "gesdinet/jwt-refresh-token-bundle": "~0.1",
    "doctrine/orm": "^2.4.8",
    "doctrine/doctrine-bundle": "~1.4",
    "doctrine/mongodb-odm-bundle": "^3.4"
    // ...

Alternatively, custom implementation of persistence can be used.

For that purpose
* provide an implementation of `Doctrine\Persistence\ObjectManager`
* configure the bundle according to
    * [object manager](#use-another-object-manager)
    * [disabling Doctrine mappings](#disable-automatic-doctrine-mappings)

### Step 2: Enable the Bundle

**Symfony 3 Version:**  
Register bundle into `app/AppKernel.php`:

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

**Symfony 4 Version:**   
Register bundle into `config/bundles.php` (Flex did it automatically):  
```php 
return [
    //...
    Gesdinet\JWTRefreshTokenBundle\GesdinetJWTRefreshTokenBundle::class => ['all' => true],
];
```

### Step 3: Configure your own routing to refresh token

Open your main routing configuration file and copy the following four lines at the very beginning of it.

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
# config/routes.yml
gesdinet_jwt_refresh_token:
    path:       /api/token/refresh
    controller: gesdinet.jwtrefreshtoken::refresh
# ...
```

### Step 4: Allow anonymous access to refresh token

Add next lines on security.yml file:

```yaml
# app/config/security.yml or config/packages/security.yaml
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

### Step 5: Update your schema

With the next command you will create a new table to handle your refresh tokens

**Symfony 3 Version:**   
```bash
php bin/console doctrine:schema:update --force

# or make and run a migration
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

**Symfony 4 Version:**   
```bash
php bin/console doctrine:schema:update --force

# or make and run a migration:
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

USAGE
-----

The configurations can be put in:

**Symfony 3 Version:** `app/config`  
**Symfony 4 Version:** `config/packages/gesdinet_jwt_refresh_token.yaml`

### Config TTL

You can define Refresh Token TTL. Default value is 1 month. You can change this value adding this line to your config:

```yaml
gesdinet_jwt_refresh_token:
    ttl: 2592000
```

### Config User identity field

You can change user identity field. Make sure that your model user has `getter` for this field. Default value is `username`. You can change this value by adding this line to your config:

```yaml
gesdinet_jwt_refresh_token:
    user_identity_field: email
```

### Config TTL update

You can expand Refresh Token TTL on refresh. Default value is false. You can change this value adding this line to your config:

```yaml
gesdinet_jwt_refresh_token:
    ttl_update: true
```

This will reset the token TTL each time you ask a refresh.

### Config Firewall Name

You can define Firewall name. Default value is api. You can change this value adding this line to your config:

```yaml
gesdinet_jwt_refresh_token:
    firewall: api
```

### Config Refresh token parameter Name

You can define refresh token parameter name. Default value is refresh_token. You can change this value adding this line to your config file:

```yaml
gesdinet_jwt_refresh_token:
    token_parameter_name: refreshToken
```

### Config UserProvider

You can define your own UserProvider. By default we use our custom UserProvider. You can change this value by adding this line to your config:

```yaml
gesdinet_jwt_refresh_token:
    user_provider: user_provider_service_id
```

For example, if you are using FOSUserBundle, user_provider_service_id must be set to `fos_user.user_provider.username_email`.

For Doctrine ORM UserProvider, user_provider_service_id must be set to `security.user.provider.concrete.<your_user_provider_name_in_security_yaml>`.
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

### Select Manager Type

By default manager type is set to use Doctrine's ORM, if you want to use Doctrine's MongoDB ODM you have to change this value:

```yaml
gesdinet_jwt_refresh_token:
    manager_type: mongodb
```

### Config UserChecker

You can define your own UserChecker. By default the Symfony UserChecker will be used. You can change this value by adding this line to your config:

```yaml
gesdinet_jwt_refresh_token:
    user_checker: user_checker_service_id
```

You will probably want to use a custom UserProvider along with your UserChecker to ensure that the checker recieves the right type of user.

### Config Single Use

You can configure the refresh token so it can only be consumed _once_. If set to `true` and the refresh token is consumed, a new refresh token will be provided. 

To enable this behavior add this line to your config:

```yaml
gesdinet_jwt_refresh_token:
    single_use: true
```


### Use another entity for refresh tokens

You can define your own refresh token class on your project.

When using default ORM create the entity class extending `Gesdinet\JWTRefreshTokenBundle\Entity\AbstractRefreshToken` in your own bundle:

```php
namespace MyBundle;

use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\AbstractRefreshToken;

/**
 * This class override Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken to have another table name.
 *
 * @ORM\Table("jwt_refresh_token")
 */
class JwtRefreshToken extends AbstractRefreshToken
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->id;
    }
}
```

When using MongoBD ODM create the document class extending `Gesdinet\JWTRefreshTokenBundle\Document\AbstractRefreshToken` in you own bundle:

```php
namespace MyBundle;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Gesdinet\JWTRefreshTokenBundle\Document\AbstractRefreshToken;

/**
 * This class override Gesdinet\JWTRefreshTokenBundle\Document\RefreshToken to have another collection name.
 *
 * @MongoDB\Document(collection="UserRefreshToken")
 */
class JwtRefreshToken extends AbstractRefreshToken
{
    /**
     * @var string
     *
     * @MongoDB\Id(strategy="auto")
     */
    protected $id;

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->id;
    }
}
```

Then declare this class adding this line to your config.yml file:

```yaml
gesdinet_jwt_refresh_token:
    refresh_token_class: MyBundle\JwtRefreshToken
```

### Use another object manager

You can tell JWTRefreshTokenBundle to use another object manager than default one (if using ORM it is doctrine.orm.entity_manager, when using MongoDB ODM it is doctrine_mongodb.odm.document_manager).

Just add this line to your config.yml file:

```yaml
gesdinet_jwt_refresh_token:
    object_manager: my.specific.entity_manager.id
```

### Disable automatic Doctrine mappings

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

This refresh token is persisted in RefreshToken entity. After that, when your JWT valid token expires, if you want to get a new one you can proceed in two ways:

- Send you user credentials again to /api/login_check. This generates another JWT with another Refresh Token.

- Ask to renew valid JWT with our refresh token. Make a POST call to /api/token/refresh url with refresh token as payload. In this way, you can always get a valid JWT without asking for user credentials. But **you must notice** if refresh token is still valid. Your refresh token do not change but valid datetime will increase.

***Note that when a refresh token is consumed and the config option `single_use` is set to `true` the token will no longer be valid.***

```bash
curl -X POST -d refresh_token="xxxx4b54b0076d2fcc5a51a6e60c0fb83b0bc90b47e2c886accb70850795fb311973c9d101fa0111f12eec739db063ec09d7dd79331e3148f5fc6e9cb362xxxx" 'http://xxxx/token/refresh'
```

This call returns a new valid JWT token renewing valid datetime of your refresh token.

Useful Commands
---------------

We give you two commands to manage tokens.

### Revoke all invalid tokens

If you want to revoke all invalid (datetime expired) refresh tokens you can execute:

**Symfony 3 Version:**
```bash
php bin/console gesdinet:jwt:clear
```

**Symfony 4 Version:**
```bash
php bin/console gesdinet:jwt:clear
```

Optional argument is datetime, it deletes all tokens smaller than this datetime:

**Symfony 3 Version:**
```bash
php bin/console gesdinet:jwt:clear 2015-08-08
```

**Symfony 4 Version:**
```bash
php bin/console gesdinet:jwt:clear 2015-08-08
```

We recommend to execute this command with a cronjob to remove invalid refresh tokens every certain time.

### Revoke a token

If you want to revoke a single token you can use this:

**Symfony 3 Version:**
```bash
php bin/console gesdinet:jwt:revoke TOKEN
```

**Symfony 4 Version:**
```bash
php bin/console gesdinet:jwt:revoke TOKEN
```

### Events

If you want to do something when token is refreshed you can listen for `gesdinet.refresh_token` event.

For example:

```php
<?php

namespace AppBundle\EventListener;

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
        $user = $event->getPreAuthenticatedToken()->getUser()->getUsername();
        
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
