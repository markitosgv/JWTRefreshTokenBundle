JWTRefreshTokenBundle
=====================

The purpose of this bundle is to handle a easy way to manage refresh tokens with JWT (Json Web Tokens). This bundles uses [LexikJWTAuthenticationBundle](https://github.com/lexik/LexikJWTAuthenticationBundle) and [FOSUserBundle](https://github.com/FriendsOfSymfony/FOSUserBundle).

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

Add next line on security.yml file:

```yaml
# app/config/security.yml
    access_control:
        // ...
        - { path: ^/token/refresh, roles: IS_AUTHENTICATED_ANONYMOUSLY }
        //...
# ...
```

### Step 5: Extend your user entity from UserRefreshToken

```php
<?php
//AppBundle\Entity\User.php

namespace AppBundle\Entity;

use Gesdinet\JWTRefreshTokenBundle\Entity\UserRefreshToken as BaseUser;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table()
 */
class User extends BaseUser {

    //...my own user entity data
}
```
### Step 6: Update your schema

Add refresh_token field to User table

```bash
php app/console doctrine:schema:update --force
```

USAGE
-----

When you authenticate through /api/login_check with user/password credentials LexikJWTAuthenticationBundle now returns a JWT Token and a Refresh Token data.

```json
{
  "token": "eyxxxGciOiJSUzI1NiIsInR5cCI6IkpXUyJ9.eyJleHAiOjE0NDI0MDM3NTgsImVtYWlsIjoid2VibWFzdGVyQGdlc2RpbmV0LmNvbSIsImlhdCI6IjE0NDI0MDM3MzgifQ.bo5pre_v0moCXVOZOj-s85gVnBLzdSdsltPn3XrkmJaE8eaBo_zcU2pnjs4dUc9hhwNZK8PL6SmSNcQuTUj4OMK7sUDfXr62a05Ds-UgQP8B2Kpc-ZOmSts_vhgo6xJNCy8Oub9-pRA_78WzUUxt294w0IArrNlgQAGewk65RSMThOif9G6L7HzBM4ajFZ-kMDypz2zVQea1kry-m-XXKNDbERCSHnMeV3rANN48SX645_WEvwaHy0agChR4hTnThzLof2bShA7j7HmnSPpODxQszS5ZBHdMgTvYhlcWJmwYswCWCTPl3lsqVq_UOFI5_4arpSNlUwZsichqxXVAHX5idZqCWtoaqAbvNQe2IpinYajoXw-MlYKvcN2TLUF_8sy529olLUagf4FCpCO6JFxovv0E7ll9tUOVvx9LlannqV8976q5XCOoXszKonZSH7DhsBlW5Emjv7PailbARZ-hfl4YlamyY2QbnxAswYycfoxqJxbbIKYGA8dlebdvMyC7m9VATnasTuKeEKS3mP5iyDgWALBHNYXm1FM-12zHBdN3PbOgxmy_OBGvk05thYFEf2WVmyedtFHy4TGlI0-otUTAf2swQAXWhKtkLWzokWWF7l5iNzam1kkEgql5EOztXHDZpmdKVHWBVNvN3J5ivPjjJBm6sGusf-radcw",
  "refresh_token": "xxx00a7a9e970f9bbe076e05743e00648908c38366c551a8cdf524ba424fc3e520988f6320a54989bbe85931ffe1bfcc63e33fd8b45d58564039943bfbd8dxxx"
}
```

This refresh token is persisted in User. When your JWT token expires magic starts:

You can generate another valid JWT in two ways, 

First way is as always, sending again user credentials to /api/login_check. This generates antoher new JWT and another new refresh token.

**Second way** is ask for a new JWT with our refresh token. We need to make a POST call to /token/refresh url with refresh token as payload.

```bash
curl -X POST -d refresh_token="xxxx4b54b0076d2fcc5a51a6e60c0fb83b0bc90b47e2c886accb70850795fb311973c9d101fa0111f12eec739db063ec09d7dd79331e3148f5fc6e9cb362xxxx" 'http://xxxx/token/refresh'
```

This calls returns a new JWT token with a new refresh token.

**Now we have a method to regenerate a JWT with refresh token and without user credentials.**

**NOTE**: Always we regenerate JWT token, refresh token be regenerated too.

KNOWN PROBLEMS
--------------

* If our user have saved refresh token in local mobile app and makes a login from another device or platform this refresh token will be invalidate cause we only store one valid refresh token.
