JWTRefreshTokenBundle
=====================

[![Latest Stable Version](https://poser.pugx.org/gesdinet/gesdinet-jwt-refresh-token-bundle/v/stable)](https://packagist.org/packages/gesdinet/gesdinet-jwt-refresh-token-bundle) 
[![Total Downloads](https://poser.pugx.org/gesdinet/gesdinet-jwt-refresh-token-bundle/downloads)](https://packagist.org/packages/gesdinet/gesdinet-jwt-refresh-token-bundle) 
[![Latest Unstable Version](https://poser.pugx.org/gesdinet/gesdinet-jwt-refresh-token-bundle/v/unstable)](https://packagist.org/packages/gesdinet/gesdinet-jwt-refresh-token-bundle) 
[![License](https://poser.pugx.org/gesdinet/gesdinet-jwt-refresh-token-bundle/license)](https://packagist.org/packages/gesdinet/gesdinet-jwt-refresh-token-bundle)
[![StyleCI](https://styleci.io/repos/42582199/shield)](https://styleci.io/repos/42582199)

The purpose of this bundle is manage refresh tokens with JWT (Json Web Tokens) in an easy way. This bundles uses [LexikJWTAuthenticationBundle](https://github.com/lexik/LexikJWTAuthenticationBundle) and [FOSUserBundle](https://github.com/FriendsOfSymfony/FOSUserBundle). At the moment only supports Doctrine ORM.

Prerequisites
-------------

This bundle requires Symfony 2.3+.

**Protip:** Though the bundle doesn't enforce you to do so, it is highly recommended to use HTTPS. 

Installation
------------

### Step 1: Download the Bundle

Add [`gesdinet/gesdinet-jwt-refresh-token-bundle`](https://packagist.org/packages/gesdinet/gesdinet-jwt-refresh-token-bundle) to your `composer.json` file:

```bash
$ composer require "gesdinet/gesdinet-jwt-refresh-token-bundle"
```

or edit composer.json:
    
    // ...
    "gesdinet/gesdinet-jwt-refresh-token-bundle": "dev-master",
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

### Step 3: Load the Routes of the Bundle

Open your main routing configuration file (usually `app/config/routing.yml`) and copy the following four lines at the very beginning of it:

```yaml
# app/config/routing.yml
gesdinet_jwt_refresh_token:
    resource: "@GesdinetJWTRefreshTokenBundle/Controller/"
    type:     annotation
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
    //...
    
    access_control:
        // ...
        - { path: ^api/token/refresh, roles: IS_AUTHENTICATED_ANONYMOUSLY }
        //...
# ...
```

### Step 5: Declare User entity

You need to specify WHERE is your FOSUserBundle entity to resolve many-to-one relationship with our UserRefreshToken entity

```yaml
orm:
    resolve_target_entities:
        Gesdinet\JWTRefreshTokenBundle\Model\UserRefreshTokenInterface: AppBundle\Entity\User
```

### Step 6: Update your schema

With the next command you will create a new table to handle your users refresh tokens

```bash
php app/console doctrine:schema:update --force
```

USAGE
-----

### Config TTL

You must define Refresh Token TTL. Default value is 1 month. You can change this value adding this line to your config.yml file:

```yaml
gesdinet_jwt_refresh_token:
    ttl: 2592000
```

### Generating Tokens

When you authenticate through /api/login_check with user/password credentials, LexikJWTAuthenticationBundle now returns a JWT Token and a Refresh Token data.

```json
{
  "token": "eyxxxGciOiJSUzI1NiIsInR5cCI6IkpXUyJ9.eyJleHAiOjE0NDI0MDM3NTgsImVtYWlsIjoid2VibWFzdGVyQGdlc2RpbmV0LmNvbSIsImlhdCI6IjE0NDI0MDM3MzgifQ.bo5pre_v0moCXVOZOj-s85gVnBLzdSdsltPn3XrkmJaE8eaBo_zcU2pnjs4dUc9hhwNZK8PL6SmSNcQuTUj4OMK7sUDfXr62a05Ds-UgQP8B2Kpc-ZOmSts_vhgo6xJNCy8Oub9-pRA_78WzUUxt294w0IArrNlgQAGewk65RSMThOif9G6L7HzBM4ajFZ-kMDypz2zVQea1kry-m-XXKNDbERCSHnMeV3rANN48SX645_WEvwaHy0agChR4hTnThzLof2bShA7j7HmnSPpODxQszS5ZBHdMgTvYhlcWJmwYswCWCTPl3lsqVq_UOFI5_4arpSNlUwZsichqxXVAHX5idZqCWtoaqAbvNQe2IpinYajoXw-MlYKvcN2TLUF_8sy529olLUagf4FCpCO6JFxovv0E7ll9tUOVvx9LlannqV8976q5XCOoXszKonZSH7DhsBlW5Emjv7PailbARZ-hfl4YlamyY2QbnxAswYycfoxqJxbbIKYGA8dlebdvMyC7m9VATnasTuKeEKS3mP5iyDgWALBHNYXm1FM-12zHBdN3PbOgxmy_OBGvk05thYFEf2WVmyedtFHy4TGlI0-otUTAf2swQAXWhKtkLWzokWWF7l5iNzam1kkEgql5EOztXHDZpmdKVHWBVNvN3J5ivPjjJBm6sGusf-radcw",
  "refresh_token": "xxx00a7a9e970f9bbe076e05743e00648908c38366c551a8cdf524ba424fc3e520988f6320a54989bbe85931ffe1bfcc63e33fd8b45d58564039943bfbd8dxxx"
}
```

This refresh token is persisted in UserRefreshToken entity. After that, when your JWT valid token expires, if you want to get a new one you can proceed in two ways:

- Send you user credentials again to /api/login_check. This generates another JWT with another Refresh token.

- Ask for a new valid JWT with our refresh token. Make a POST call to /api/token/refresh url with refresh token and username as payload. In this way, you can always get a valid JWT without asking for user credentials. But **you must notice** if refresh token is still valid.

```bash
curl -X POST -d username="xxx@xxx.com" -d refresh_token="xxxx4b54b0076d2fcc5a51a6e60c0fb83b0bc90b47e2c886accb70850795fb311973c9d101fa0111f12eec739db063ec09d7dd79331e3148f5fc6e9cb362xxxx" 'http://xxxx/token/refresh'
```

This call returns a new valid JWT token with a new refresh token.

Useful Commands
---------------

We give you two commands to manage tokens.

### Revoke all invalid tokens

If you want to revoke all invalid (datetime expired) refresh tokens you can execute:

```bash
php app/console gesdinet:jwt:clear
```

Optional argument is datetime:

```bash
php app/console gesdinet:jwt:clear 2015-08-08
```

We recommend to execute this command with a cronjob to remove invalid refresh tokens every certain time.

### Revoke a token

If you want to revoke one token you can use this:

```bash
php app/console gesdinet:jwt:revoke TOKEN USER
```