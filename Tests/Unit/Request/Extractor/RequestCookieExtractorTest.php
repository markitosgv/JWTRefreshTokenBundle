<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Request\Extractor;

use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\RequestCookieExtractor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

class RequestCookieExtractorTest extends TestCase
{
    private const PARAMETER_NAME = 'refresh_token';
    private RequestCookieExtractor $requestCookieExtractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requestCookieExtractor = new RequestCookieExtractor();
    }

    public function testIsAnExtractor(): void
    {
        $this->assertInstanceOf(ExtractorInterface::class, $this->requestCookieExtractor);
    }

    public function testGetsTheTokenFromTheRequestCookies(): void
    {
        $token = 'my-refresh-token';

        /** @var ParameterBag|MockObject $cookieBag */
        $cookieBag = $this->createMock(ParameterBag::class);
        $cookieBag
            ->expects($this->once())
            ->method('get')
            ->with(self::PARAMETER_NAME)
            ->willReturn($token);

        /** @var Request|MockObject $request */
        $request = $this->createMock(Request::class);
        $request->cookies = $cookieBag;

        $this->assertSame(
            $token,
            $this->requestCookieExtractor->getRefreshToken($request, self::PARAMETER_NAME)
        );
    }
}
