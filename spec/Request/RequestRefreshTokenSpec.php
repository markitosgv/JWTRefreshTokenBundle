<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Request;

use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\Request;

class RequestRefreshTokenSpec extends ObjectBehavior
{
    public function it_gets_from_query_param()
    {
        $request = Request::createFromGlobals();
        $request->attributes->set('refresh_token', 'abcd');

        $this::getRefreshToken($request)->shouldBe('abcd');
    }

    public function it_gets_from_body()
    {
        $request = Request::createFromGlobals();
        $request->request->set('refresh_token', 'abcd');

        $this::getRefreshToken($request)->shouldBe('abcd');
    }

    public function it_gets_from_json()
    {
        $request = Request::create(null, 'POST', array(), array(), array(), array(), json_encode(array('refresh_token' => 'abcd')));
        $request->headers->set('content_type', 'application/json');

        $this::getRefreshToken($request)->shouldBe('abcd');
    }

    public function it_gets_from_json_x()
    {
        $request = Request::create(null, 'POST', array(), array(), array(), array(), json_encode(array('refresh_token' => 'abcd')));
        $request->headers->set('content_type', 'application/x-json');

        $this::getRefreshToken($request)->shouldBe('abcd');
    }

    public function it_gets_from_json_parameter()
    {
        $request = Request::create(null, 'POST', array(), array(), array(), array(), json_encode(array('refresh_token' => 'abcd')));
        $request->headers->set('content_type', 'application/json;charset=UTF-8');

        $this::getRefreshToken($request)->shouldBe('abcd');
    }
}
