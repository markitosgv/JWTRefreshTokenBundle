<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Request\Extractor;

use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\Request;

final class ChainExtractorSpec extends ObjectBehavior
{
    private const PARAMETER_NAME = 'refresh_token';

    public function it_is_an_extractor(): void
    {
        $this->shouldImplement(ExtractorInterface::class);
    }

    public function it_gets_the_token_from_the_first_extractor_in_the_chain(
        ExtractorInterface $firstExtractor,
        ExtractorInterface $secondExtractor,
        ExtractorInterface $thirdExtractor,
        Request $request
    ): void {
        $token = 'my-refresh-token';

        $firstExtractor->getRefreshToken($request, self::PARAMETER_NAME)->willReturn(null);
        $secondExtractor->getRefreshToken($request, self::PARAMETER_NAME)->willReturn($token);
        $thirdExtractor->getRefreshToken($request, self::PARAMETER_NAME)->shouldNotBeCalled();

        $this->addExtractor($firstExtractor);
        $this->addExtractor($secondExtractor);
        $this->addExtractor($thirdExtractor);

        $this->getRefreshToken($request, self::PARAMETER_NAME)->shouldReturn($token);
    }

    public function it_provides_no_token_if_none_of_the_extractors_returns_one(
        ExtractorInterface $firstExtractor,
        ExtractorInterface $secondExtractor,
        ExtractorInterface $thirdExtractor,
        Request $request
    ): void {
        $firstExtractor->getRefreshToken($request, self::PARAMETER_NAME)->willReturn(null);
        $secondExtractor->getRefreshToken($request, self::PARAMETER_NAME)->willReturn(null);
        $thirdExtractor->getRefreshToken($request, self::PARAMETER_NAME)->willReturn(null);

        $this->addExtractor($firstExtractor);
        $this->addExtractor($secondExtractor);
        $this->addExtractor($thirdExtractor);

        $this->getRefreshToken($request, self::PARAMETER_NAME)->shouldReturn(null);
    }
}
