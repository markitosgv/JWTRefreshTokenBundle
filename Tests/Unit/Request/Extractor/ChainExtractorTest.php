<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Request\Extractor;

use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ChainExtractor;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class ChainExtractorTest extends TestCase
{
    private const PARAMETER_NAME = 'refresh_token';

    private ChainExtractor $chainExtractor;

    protected function setUp(): void
    {
        $this->chainExtractor = new ChainExtractor();
    }

    public function testGetsTheTokenFromTheFirstExtractorInTheChain(): void
    {
        /** @var ExtractorInterface|MockObject $firstExtractor */
        $firstExtractor = $this->createMock(ExtractorInterface::class);

        /** @var ExtractorInterface|MockObject $secondExtractor */
        $secondExtractor = $this->createMock(ExtractorInterface::class);

        /** @var ExtractorInterface|MockObject $thirdExtractor */
        $thirdExtractor = $this->createMock(ExtractorInterface::class);

        /** @var Request|MockObject $request */
        $request = $this->createMock(Request::class);

        $token = 'my-refresh-token';

        $this->createExtractorGetRefreshTokenExpectation($firstExtractor, null);
        $this->createExtractorGetRefreshTokenExpectation($secondExtractor, $token);
        $thirdExtractor
            ->expects($this->never())
            ->method('getRefreshToken');

        $this->chainExtractor->addExtractor($firstExtractor);
        $this->chainExtractor->addExtractor($secondExtractor);
        $this->chainExtractor->addExtractor($thirdExtractor);

        $this->assertSame($token, $this->chainExtractor->getRefreshToken($request, self::PARAMETER_NAME));
    }

    public function testProvidesNoTokenIfNoneOfTheExtractorsReturnsOne(): void
    {
        /** @var ExtractorInterface|MockObject $firstExtractor */
        $firstExtractor = $this->createMock(ExtractorInterface::class);

        /** @var ExtractorInterface|MockObject $secondExtractor */
        $secondExtractor = $this->createMock(ExtractorInterface::class);

        /** @var ExtractorInterface|MockObject $thirdExtractor */
        $thirdExtractor = $this->createMock(ExtractorInterface::class);

        /** @var Request|MockObject $request */
        $request = $this->createMock(Request::class);

        $this->createExtractorGetRefreshTokenExpectation($firstExtractor, null);
        $this->createExtractorGetRefreshTokenExpectation($secondExtractor, null);
        $this->createExtractorGetRefreshTokenExpectation($thirdExtractor, null);

        $this->chainExtractor->addExtractor($firstExtractor);
        $this->chainExtractor->addExtractor($secondExtractor);
        $this->chainExtractor->addExtractor($thirdExtractor);

        $this->assertSame(null, $this->chainExtractor->getRefreshToken($request, self::PARAMETER_NAME));
    }

    private function createExtractorGetRefreshTokenExpectation(MockObject $extractor, ?string $return): void
    {
        $extractor
            ->expects($this->once())
            ->method('getRefreshToken')
            ->willReturn($return);
    }
}
