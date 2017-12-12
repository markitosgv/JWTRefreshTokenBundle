<?php

namespace Gesdinet\JWTRefreshTokenBundle\NameGenerator;

use Doctrine\Common\Inflector\Inflector;

/**
 * Name generator for underscore names.
 *
 * @see UnderscoreNameGeneratorSpec for unit tests
 */
class UnderscoreNameGenerator implements NameGeneratorInterface
{
    /**
     * @param string $name
     *
     * @return string
     */
    public function generateName($name)
    {
        return Inflector::tableize($name);
    }
}
