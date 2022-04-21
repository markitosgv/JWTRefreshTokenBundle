<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Request\Extractor;

use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\RequestBodyExtractor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class RequestBodyExtractorTest extends TestCase
{
    private const PARAMETER_NAME = 'refresh_token';
    private RequestBodyExtractor $requestBodyExtractor;

    protected function setUp(): void
    {
        $this->requestBodyExtractor = new RequestBodyExtractor();
    }

    public function testGetsTheTokenFromTheRequestBody(): void
    {
        $token = 'my-refresh-token';
        $request = $this->createMockRequest('json', [self::PARAMETER_NAME => $token]);

        $this->assertSame(
            $token,
            $this->requestBodyExtractor->getRefreshToken($request, self::PARAMETER_NAME)
        );
    }

    public function testReturnsNullIfTheParameterDoesNotExistInTheRequestBody(): void
    {
        $request = $this->createMockRequest('json', []);

        $this->assertNull($this->requestBodyExtractor->getRefreshToken($request, self::PARAMETER_NAME));
    }

    public function testReturnsNullIfTheRequestIsNotAJsonType(): void
    {
        $request = $this->createMockRequest(null);

        $this->assertNull($this->requestBodyExtractor->getRefreshToken($request, self::PARAMETER_NAME));
    }

    /**
     * @return Request|MockObject
     */
    private function createMockRequest(?string $contentType, ?array $jsonBodyData = null): MockObject
    {
        /** @var Request|MockObject $request */
        $request = $this->createMock(Request::class);

        $request
            ->expects($this->atLeastOnce())
            ->method(method_exists(Request::class, 'getContentTypeFormat') ? 'getContentTypeFormat' : 'getContentType')
            ->willReturn($contentType);

        if (is_array($jsonBodyData)) {
            $request
                ->expects($this->once())
                ->method('getContent')
                ->willReturn(json_encode($jsonBodyData));
        }

        return $request;
    }
}
