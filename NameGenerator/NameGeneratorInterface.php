<?php

namespace Gesdinet\JWTRefreshTokenBundle\NameGenerator;

/**
 * Interface for parameter naming generators.
 */
interface NameGeneratorInterface
{
    /**
     * Convert the given name to the generator's styled version.
     *
     * @param string $name
     *
     * @return string
     */
    public function generateName($name);
}
