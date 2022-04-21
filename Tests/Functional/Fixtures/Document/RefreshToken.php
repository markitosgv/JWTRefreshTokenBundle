<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Gesdinet\JWTRefreshTokenBundle\Document\RefreshToken as BaseRefreshToken;

#[ODM\Document]
class RefreshToken extends BaseRefreshToken
{
}
