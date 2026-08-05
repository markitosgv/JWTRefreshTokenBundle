<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Request\Extractor;

use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\RequestCookieExtractor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class RequestCookieExtractorTest extends TestCase
{
    private const string PARAMETER_NAME = 'refresh_token';
    private RequestCookieExtractor $requestCookieExtractor;

    protected function setUp(): void
    {
        $this->requestCookieExtractor = new RequestCookieExtractor();
    }

    public function testGetsTheTokenFromTheRequestCookies(): void
    {
        $token = 'my-refresh-token';

        $request = new Request(cookies: [self::PARAMETER_NAME => $token]);

        $this->assertSame(
            $token,
            $this->requestCookieExtractor->getRefreshToken($request, self::PARAMETER_NAME)
        );
    }
}
