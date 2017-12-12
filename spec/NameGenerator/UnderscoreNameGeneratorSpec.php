<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\NameGenerator;

use Gesdinet\JWTRefreshTokenBundle\NameGenerator\NameGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\NameGenerator\UnderscoreNameGenerator;
use PhpSpec\ObjectBehavior;

/**
 * Spec for Gesdinet\JWTRefreshTokenBundle\NameGenerator\UnderscoreNameGenerator.
 *
 * @covers  \Gesdinet\JWTRefreshTokenBundle\NameGenerator\UnderscoreNameGenerator
 */
class UnderscoreNameGeneratorSpec extends ObjectBehavior
{
    //------------------------------------------------------------------------------------------------------------------
    // Spec: Class / interfaces
    //------------------------------------------------------------------------------------------------------------------

    public function it_is_initializable()
    {
        $this->shouldHaveType(UnderscoreNameGenerator::class);
    }

    public function it_implements_name_generator_interface()
    {
        $this->shouldImplement(NameGeneratorInterface::class);
    }

    public function it_should_return_underscored_values_from_snake_case()
    {
        /* @var UnderscoreNameGenerator|UnderscoreNameGeneratorSpec $this */

        // Method under test
        $this->generateName('refresh_token')
            ->shouldReturn('refresh_token');
    }

    public function it_should_return_underscored_values_from_camel_case()
    {
        /* @var UnderscoreNameGenerator|UnderscoreNameGeneratorSpec $this */

        // Method under test
        $this->generateName('refreshToken')
             ->shouldReturn('refresh_token');
    }
}
