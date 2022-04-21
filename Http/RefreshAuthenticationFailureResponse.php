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

class RefreshAuthenticationFailureResponse extends JsonResponse
{
    private string $message;

    public function __construct(string $message = 'Bad credentials', int $statusCode = Response::HTTP_UNAUTHORIZED)
    {
        $this->message = $message;

        parent::__construct(null, $statusCode);
    }

    public function setData(mixed $data = []): static
    {
        return parent::setData((array) $data + ['code' => $this->statusCode, 'message' => $this->getMessage()]);
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
