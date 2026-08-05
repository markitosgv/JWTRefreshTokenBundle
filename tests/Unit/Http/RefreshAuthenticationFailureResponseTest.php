<?php

declare(strict_types=1);

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Http;

use Gesdinet\JWTRefreshTokenBundle\Http\RefreshAuthenticationFailureResponse;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class RefreshAuthenticationFailureResponseTest extends TestCase
{
    public function test_carries_the_message_and_the_status_code_in_the_body(): void
    {
        $response = new RefreshAuthenticationFailureResponse('Bad credentials');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertSame('Bad credentials', $response->getMessage());
        $this->assertSame(
            ['code' => Response::HTTP_UNAUTHORIZED, 'message' => 'Bad credentials'],
            json_decode((string) $response->getContent(), true)
        );
    }

    public function test_rewrites_the_body_when_the_message_is_replaced(): void
    {
        $response = new RefreshAuthenticationFailureResponse('Bad credentials');

        $response->setMessage('Replaced by a listener');

        $this->assertSame('Replaced by a listener', $response->getMessage());
        $this->assertSame(
            ['code' => Response::HTTP_UNAUTHORIZED, 'message' => 'Replaced by a listener'],
            json_decode((string) $response->getContent(), true)
        );
    }
}
