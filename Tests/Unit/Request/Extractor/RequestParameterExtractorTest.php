<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Request\Extractor;

use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\RequestParameterExtractor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class RequestParameterExtractorTest extends TestCase
{
    private const PARAMETER_NAME = 'refresh_token';

    private RequestParameterExtractor $requestParameterExtractor;

    protected function setUp(): void
    {
        $this->requestParameterExtractor = new RequestParameterExtractor();
    }

    public function testGetsTheTokenFromTheRequestParameters(): void
    {
        /** @var Request|MockObject $request */
        $request = $this->createMock(Request::class);
        $token = 'my-refresh-token';

        $request
            ->expects($this->once())
            ->method('get')
            ->with(self::PARAMETER_NAME)
            ->willReturn($token);

        $this->assertSame(
            $token,
            $this->requestParameterExtractor->getRefreshToken($request, self::PARAMETER_NAME)
        );
    }
}
