<?php

/*
 * This file is part of the Gesdinet JWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\OpenApi;
use Symfony\Component\HttpFoundation\Response;

/**
 * Documents the refresh token in the OpenAPI specification API Platform generates.
 *
 * LexikJWTAuthenticationBundle documents the login endpoint, but its response schema only carries
 * the JWT, because the refresh token beside it is added by this bundle and that factory has no
 * hook to extend. The refresh endpoint is not documented by anyone: it is a firewall authenticator,
 * so there is no controller or resource for API Platform to find.
 *
 * This decorates the same factory at a lower priority, so it is handed the specification Lexik has
 * already contributed to and completes it.
 *
 * Written by hand in an application, this has to repeat configuration the bundle already holds and
 * gets one case wrong in particular: with the cookie enabled and the token removed from the body,
 * there is no `refresh_token` property to document at all, and the client is meant to let the
 * browser carry the cookie instead.
 */
final readonly class OpenApiFactory implements OpenApiFactoryInterface
{
    /**
     * The operation id Lexik gives the login endpoint, which is how it is found again here without
     * having to be told the login path a second time.
     */
    private const LOGIN_OPERATION_ID = 'login_check_post';

    /**
     * @param string[]             $checkPaths     the refresh paths, one per firewall the authenticator is enabled on
     * @param array<string, mixed> $cookieSettings
     *
     * @psalm-mutation-free
     */
    public function __construct(
        private OpenApiFactoryInterface $decorated,
        private array $checkPaths,
        private string $tokenParameterName,
        private bool $returnExpiration,
        private string $returnExpirationParameterName,
        private array $cookieSettings,
    ) {
    }

    #[\Override]
    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        $this->completeTheLoginResponse($openApi);

        foreach ($this->checkPaths as $checkPath) {
            $openApi->getPaths()->addPath($checkPath, (new PathItem())->withPost($this->refreshOperation()));
        }

        return $openApi;
    }

    /**
     * Adds what this bundle puts beside the JWT to whatever Lexik documented the login as returning.
     *
     * Nothing happens when Lexik's API Platform integration is off, since then there is no login
     * endpoint in the specification to complete.
     */
    private function completeTheLoginResponse(OpenApi $openApi): void
    {
        foreach ($openApi->getPaths()->getPaths() as $path => $pathItem) {
            if (!is_string($path) || !$pathItem instanceof PathItem) {
                continue;
            }

            $operation = $pathItem->getPost();

            if (null === $operation || self::LOGIN_OPERATION_ID !== $operation->getOperationId()) {
                continue;
            }

            $responses = $operation->getResponses() ?? [];
            $ok = $responses[Response::HTTP_OK] ?? null;

            if (!is_array($ok)) {
                continue;
            }

            $content = self::arrayAt($ok, 'content');
            $json = self::arrayAt($content, 'application/json');
            $schema = self::arrayAt($json, 'schema');
            $properties = self::arrayAt($schema, 'properties');

            // Whatever Lexik documented is left as it is if it is not the shape being completed
            if ([] === $properties) {
                continue;
            }

            $schema['properties'] = array_merge($properties, $this->responseProperties());
            $json['schema'] = $schema;
            $content['application/json'] = $json;
            $ok['content'] = $content;
            $responses[Response::HTTP_OK] = $ok;

            $openApi->getPaths()->addPath($path, $pathItem->withPost($operation->withResponses($responses)));
        }
    }

    /**
     * The OpenAPI models hold the response schemas as plain arrays, so reading down into one means
     * checking each step rather than trusting the shape somebody else built.
     *
     * @param array<mixed> $array
     *
     * @return array<mixed>
     *
     * @psalm-pure
     */
    private static function arrayAt(array $array, string $key): array
    {
        $value = $array[$key] ?? null;

        return is_array($value) ? $value : [];
    }

    private function refreshOperation(): Operation
    {
        return (new Operation())
            ->withOperationId('gesdinet_jwt_refresh_token_post')
            ->withTags(['Login Check'])
            ->withSummary('Exchanges a refresh token for a new JWT.')
            ->withDescription($this->tokenIsInTheBody()
                ? 'Returns a new JWT, and a refresh token to use for the next exchange.'
                : sprintf('The refresh token is read from the "%s" cookie, so the request needs no body. The new one is returned the same way.', $this->tokenParameterName))
            ->withRequestBody($this->tokenIsInTheBody() ? $this->refreshRequestBody() : null)
            ->withResponses([
                Response::HTTP_OK => [
                    'description' => 'JWT refreshed',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => array_merge(
                                    ['token' => ['readOnly' => true, 'type' => 'string', 'nullable' => false]],
                                    $this->responseProperties(),
                                ),
                                'required' => ['token'],
                            ],
                        ],
                    ],
                ],
                Response::HTTP_UNAUTHORIZED => [
                    'description' => 'The refresh token is missing, unknown or has expired',
                ],
            ]);
    }

    private function refreshRequestBody(): RequestBody
    {
        return (new RequestBody())
            ->withDescription('The refresh token issued alongside the JWT being replaced')
            ->withContent(new \ArrayObject([
                'application/json' => new MediaType(new \ArrayObject([
                    'type' => 'object',
                    'properties' => [
                        $this->tokenParameterName => [
                            'type' => 'string',
                            'nullable' => false,
                        ],
                    ],
                    'required' => [$this->tokenParameterName],
                ])),
            ]))
            ->withRequired(true);
    }

    /**
     * What this bundle adds to a response body, which is nothing at all when the token travels in a
     * cookie and the expiration is not asked for.
     *
     * @return array<string, array<string, mixed>>
     */
    private function responseProperties(): array
    {
        $properties = [];

        if ($this->tokenIsInTheBody()) {
            $properties[$this->tokenParameterName] = [
                'readOnly' => true,
                'type' => 'string',
                'nullable' => false,
            ];
        }

        if ($this->returnExpiration) {
            $properties[$this->returnExpirationParameterName] = [
                'readOnly' => true,
                'type' => 'integer',
                'format' => 'int64',
                'description' => 'The Unix timestamp the refresh token expires at',
                'nullable' => false,
            ];
        }

        return $properties;
    }

    /**
     * The cookie replaces the body only when it is asked to. Left enabled without that, the token
     * is in both.
     *
     * @psalm-mutation-free
     */
    private function tokenIsInTheBody(): bool
    {
        if (true !== ($this->cookieSettings['enabled'] ?? false)) {
            return true;
        }

        return true !== ($this->cookieSettings['remove_token_from_body'] ?? true);
    }
}
