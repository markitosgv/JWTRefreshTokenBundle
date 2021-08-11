<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

if (80000 <= \PHP_VERSION_ID && (new \ReflectionMethod(JsonResponse::class, 'setData'))->hasReturnType()) {
    eval('
        namespace Gesdinet\JWTRefreshTokenBundle\Http;

        use Symfony\Component\HttpFoundation\JsonResponse;

        /**
         * Compatibility layer for Symfony 6.0 and later.
         *
         * @internal
         */
        abstract class CompatRefreshAuthenticationFailureResponse extends JsonResponse
        {
            public function setData(mixed $data = []): static
            {
                return parent::setData((array) $data + ["code" => $this->statusCode, "message" => $this->getMessage()]);
            }
        }
    ');
} else {
    /**
     * Compatibility layer for Symfony 5.4 and earlier.
     *
     * @internal
     */
    abstract class CompatRefreshAuthenticationFailureResponse extends JsonResponse
    {
        public function setData($data = [])
        {
            return parent::setData((array) $data + ['code' => $this->statusCode, 'message' => $this->getMessage()]);
        }
    }
}

class RefreshAuthenticationFailureResponse extends CompatRefreshAuthenticationFailureResponse
{
    private string $message;

    public function __construct(string $message = 'Bad credentials', int $statusCode = Response::HTTP_UNAUTHORIZED)
    {
        $this->message = $message;

        parent::__construct(null, $statusCode);
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this->setData();
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
