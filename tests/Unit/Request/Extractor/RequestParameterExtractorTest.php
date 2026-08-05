<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Request\Extractor;

use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\RequestParameterExtractor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class RequestParameterExtractorTest extends TestCase
{
    private const string PARAMETER_NAME = 'refresh_token';

    private RequestParameterExtractor $requestParameterExtractor;

    protected function setUp(): void
    {
        $this->requestParameterExtractor = new RequestParameterExtractor();
    }

    public function testGetsTheTokenFromTheRequestParameters(): void
    {
        $token = 'my-refresh-token';

        $request = new Request(attributes: [self::PARAMETER_NAME => $token]);

        $this->assertSame(
            $token,
            $this->requestParameterExtractor->getRefreshToken($request, self::PARAMETER_NAME)
        );
    }
}
