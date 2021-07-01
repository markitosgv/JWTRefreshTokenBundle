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
    /**
     * @var string
     */
    private $message;

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

    public function setData($data = []): self
    {
        return parent::setData((array) $data + ['code' => $this->statusCode, 'message' => $this->message]);
    }
}
