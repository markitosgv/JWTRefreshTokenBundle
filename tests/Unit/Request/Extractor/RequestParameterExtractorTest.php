<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Request\Extractor;

use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\RequestParameterExtractor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

final class RequestParameterExtractorTest extends TestCase
{
    private const PARAMETER_NAME = 'refresh_token';

    private RequestParameterExtractor $requestParameterExtractor;

    protected function setUp(): void
    {
        $this->requestParameterExtractor = new RequestParameterExtractor();
    }

    public function testGetsTheTokenFromTheRequestParameters(): void
    {
        $token = 'my-refresh-token';

        /** @var ParameterBag&MockObject $request */
        $attributes = $this->createMock(ParameterBag::class);
        $attributes->expects($this->once())
            ->method('get')
            ->with(self::PARAMETER_NAME)
            ->willReturn($token);

        /** @var Request&MockObject $request */
        $request = $this->createMock(Request::class);
        $request->attributes = $attributes;

        $this->assertSame(
            $token,
            $this->requestParameterExtractor->getRefreshToken($request, self::PARAMETER_NAME)
        );
    }
}
