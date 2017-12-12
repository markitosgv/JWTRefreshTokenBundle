<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\NameGenerator;

use Gesdinet\JWTRefreshTokenBundle\NameGenerator\CamelCaseNameGenerator;
use Gesdinet\JWTRefreshTokenBundle\NameGenerator\NameGeneratorInterface;
use PhpSpec\ObjectBehavior;

/**
 * Spec for Gesdinet\JWTRefreshTokenBundle\NameGenerator\CamelCaseNameGenerator.
 *
 * @covers \Gesdinet\JWTRefreshTokenBundle\NameGenerator\CamelCaseNameGenerator
 */
class CamelCaseNameGeneratorSpec extends ObjectBehavior
{
    //------------------------------------------------------------------------------------------------------------------
    // Spec: Class / interfaces
    //------------------------------------------------------------------------------------------------------------------

    public function it_is_initializable()
    {
        $this->shouldHaveType(CamelCaseNameGenerator::class);
    }

    public function it_implements_name_generator_interface()
    {
        $this->shouldImplement(NameGeneratorInterface::class);
    }

    public function it_should_return_underscored_values_from_snake_case()
    {
        /* @var CamelCaseNameGenerator|CamelCaseNameGeneratorSpec $this */

        // Method under test
        $this->generateName('refresh_token')
             ->shouldReturn('refreshToken');
    }

    public function it_should_return_underscored_values_from_camel_case()
    {
        /* @var CamelCaseNameGenerator|CamelCaseNameGeneratorSpec $this */

        // Method under test
        $this->generateName('refreshToken')
             ->shouldReturn('refreshToken');
    }
}
