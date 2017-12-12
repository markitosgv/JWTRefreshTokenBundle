<?php

namespace Gesdinet\JWTRefreshTokenBundle\NameGenerator;

use Doctrine\Common\Inflector\Inflector;

/**
 * Name generator for camel case names.
 *
 * @see CamelCaseNameGeneratorSpec for unit tests
 */
class CamelCaseNameGenerator implements NameGeneratorInterface
{
    /**
     * @param string $name
     *
     * @return string
     */
    public function generateName($name)
    {
        return Inflector::camelize($name);
    }
}
