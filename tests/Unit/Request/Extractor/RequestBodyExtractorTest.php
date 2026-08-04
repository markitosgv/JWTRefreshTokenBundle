<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Request\Extractor;

use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\RequestBodyExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class RequestBodyExtractorTest extends TestCase
{
    private const PARAMETER_NAME = 'refresh_token';

    private RequestBodyExtractor $requestBodyExtractor;

    protected function setUp(): void
    {
        $this->requestBodyExtractor = new RequestBodyExtractor();
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function contentTypeProvider(): iterable
    {
        yield 'json' => ['application/json'];
        yield 'json with a charset' => ['application/json; charset=utf-8'];
        yield 'json-ld, as API Platform sends' => ['application/ld+json'];
        // What fetch() sends when it is given no headers, and what a proxy stripping the header
        // leaves behind: the body is still the JSON the client meant to send
        yield 'text, as fetch() sends without headers' => ['text/plain;charset=UTF-8'];
        yield 'none at all' => [null];
    }

    #[DataProvider('contentTypeProvider')]
    public function testGetsTheTokenFromTheRequestBodyWhateverTheRequestCallsIt(?string $contentType): void
    {
        $token = 'my-refresh-token';

        $this->assertSame($token, $this->requestBodyExtractor->getRefreshToken(
            $this->createRequest((string) json_encode([self::PARAMETER_NAME => $token]), $contentType),
            self::PARAMETER_NAME
        ));
    }

    public function testReturnsNullIfTheParameterDoesNotExistInTheRequestBody(): void
    {
        $request = $this->createRequest((string) json_encode(['something_else' => 'a-value']));

        $this->assertNull($this->requestBodyExtractor->getRefreshToken($request, self::PARAMETER_NAME));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unusableBodyProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'not json at all' => ['not json'];
        yield 'json that is not an object' => ['["a-value"]'];
        yield 'json holding only a string' => ['"a-value"'];
        yield 'the parameter holding an object' => ['{"refresh_token": {"nested": true}}'];
    }

    #[DataProvider('unusableBodyProvider')]
    public function testReturnsNullWithoutFailingOnABodyItCannotUse(string $body): void
    {
        $request = $this->createRequest($body);

        $this->assertNull($this->requestBodyExtractor->getRefreshToken($request, self::PARAMETER_NAME));
    }

    private function createRequest(string $body, ?string $contentType = 'application/json'): Request
    {
        return Request::create(
            '/token/refresh',
            'POST',
            [],
            [],
            [],
            null === $contentType ? [] : ['CONTENT_TYPE' => $contentType],
            $body
        );
    }
}
