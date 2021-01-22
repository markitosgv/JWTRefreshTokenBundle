<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Request;

use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\Request;

class RequestRefreshTokenSpec extends ObjectBehavior
{
    const TOKEN_PARAMETER_NAME = 'refresh_token';

    public function it_gets_from_query_param()
    {
        $request = Request::createFromGlobals();
        $request->attributes->set(self::TOKEN_PARAMETER_NAME, 'abcd');

        $this::getRefreshToken($request, self::TOKEN_PARAMETER_NAME)->shouldBe('abcd');
    }

    public function it_gets_from_body()
    {
        $request = Request::createFromGlobals();
        $request->request->set(self::TOKEN_PARAMETER_NAME, 'abcd');

        $this::getRefreshToken($request, self::TOKEN_PARAMETER_NAME)->shouldBe('abcd');
    }

    public function it_gets_from_json()
    {
        $request = Request::create('', 'POST', [], [], [], [], json_encode([self::TOKEN_PARAMETER_NAME => 'abcd']));
        $request->headers->set('content_type', 'application/json');

        $this::getRefreshToken($request, self::TOKEN_PARAMETER_NAME)->shouldBe('abcd');
    }

    public function it_gets_from_json_x()
    {
        $request = Request::create('', 'POST', [], [], [], [], json_encode([self::TOKEN_PARAMETER_NAME => 'abcd']));
        $request->headers->set('content_type', 'application/x-json');

        $this::getRefreshToken($request, self::TOKEN_PARAMETER_NAME)->shouldBe('abcd');
    }

    public function it_gets_from_json_parameter()
    {
        $request = Request::create('', 'POST', [], [], [], [], json_encode([self::TOKEN_PARAMETER_NAME => 'abcd']));
        $request->headers->set('content_type', 'application/json;charset=UTF-8');

        $this::getRefreshToken($request, self::TOKEN_PARAMETER_NAME)->shouldBe('abcd');
    }
}
