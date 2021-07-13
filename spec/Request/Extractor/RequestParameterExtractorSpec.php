<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Request\Extractor;

use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\Request;

final class RequestParameterExtractorSpec extends ObjectBehavior
{
    private const PARAMETER_NAME = 'refresh_token';

    public function it_is_an_extractor(): void
    {
        $this->shouldImplement(ExtractorInterface::class);
    }

    public function it_gets_the_token_from_the_request_parameters(Request $request): void
    {
        $token = 'my-refresh-token';

        $request->get(self::PARAMETER_NAME)->willReturn($token);

        $this->getRefreshToken($request, self::PARAMETER_NAME)->shouldReturn($token);
    }
}
