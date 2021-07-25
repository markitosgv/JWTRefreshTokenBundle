<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Request\Extractor;

use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\Request;

final class RequestBodyExtractorSpec extends ObjectBehavior
{
    private const PARAMETER_NAME = 'refresh_token';

    public function it_is_an_extractor(): void
    {
        $this->shouldImplement(ExtractorInterface::class);
    }

    public function it_gets_the_token_from_the_request_body(Request $request): void
    {
        $token = 'my-refresh-token';

        $request->getContentType()->willReturn('json');
        $request->getContent()->willReturn(json_encode([self::PARAMETER_NAME => $token]));

        $this->getRefreshToken($request, self::PARAMETER_NAME)->shouldReturn($token);
    }

    public function it_returns_null_if_the_parameter_does_not_exist_in_the_request_body(Request $request): void
    {
        $request->getContentType()->willReturn('json');
        $request->getContent()->willReturn(json_encode([]));

        $this->getRefreshToken($request, self::PARAMETER_NAME)->shouldReturn(null);
    }

    public function it_returns_null_if_the_request_is_not_a_json_type(Request $request): void
    {
        $request->getContentType()->willReturn('html');

        $this->getRefreshToken($request, self::PARAMETER_NAME)->shouldReturn(null);
    }
}
