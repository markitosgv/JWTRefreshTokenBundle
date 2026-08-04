<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Info;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\OpenApi;
use Gesdinet\JWTRefreshTokenBundle\OpenApi\OpenApiFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class OpenApiFactoryTest extends TestCase
{
    private const CHECK_PATH = '/api/token/refresh';
    private const LOGIN_PATH = '/api/login';

    public function testDocumentsTheRefreshEndpointTheFirewallAnswers(): void
    {
        $paths = $this->factory()()->getPaths();

        $operation = $paths->getPath(self::CHECK_PATH)?->getPost();

        $this->assertNotNull($operation, 'The refresh endpoint should be documented');
        $this->assertSame('Exchanges a refresh token for a new JWT.', $operation->getSummary());

        $properties = $this->okProperties($operation);

        $this->assertArrayHasKey('token', $properties);
        $this->assertArrayHasKey('refresh_token', $properties);
    }

    public function testAsksForTheRefreshTokenInTheRequestBody(): void
    {
        $operation = $this->factory()()->getPaths()->getPath(self::CHECK_PATH)?->getPost();

        $this->assertNotNull($operation);

        $requestBody = $operation->getRequestBody();

        $this->assertNotNull($requestBody);

        $content = $requestBody->getContent();
        $mediaType = $content?->offsetGet('application/json');

        $this->assertInstanceOf(MediaType::class, $mediaType);

        $schema = (array) $mediaType->getSchema();

        $this->assertSame(['refresh_token'], array_keys(self::arrayAt($schema, 'properties')));
        $this->assertSame(['refresh_token'], self::arrayAt($schema, 'required'));
    }

    /**
     * The endpoint is a firewall authenticator rather than a resource, so its path is only ever
     * named on the firewall, and one specification documents every firewall it is enabled on.
     */
    public function testDocumentsAPathForEveryFirewallTheAuthenticatorIsOn(): void
    {
        $openApi = $this->factory(checkPaths: ['/api/token/refresh', '/admin/token/refresh'])();

        $this->assertNotNull($openApi->getPaths()->getPath('/api/token/refresh'));
        $this->assertNotNull($openApi->getPaths()->getPath('/admin/token/refresh'));
    }

    /**
     * What the issue was about: Lexik documents the login as returning the JWT, because the refresh
     * token beside it is put there by this bundle.
     */
    public function testAddsTheRefreshTokenToTheLoginResponseLexikDocumented(): void
    {
        $openApi = $this->factory()();

        $schema = $this->okSchema($openApi, self::LOGIN_PATH);

        $this->assertSame(['token', 'refresh_token'], array_keys(self::arrayAt($schema, 'properties')));
        $this->assertSame(['token'], self::arrayAt($schema, 'required'), 'What Lexik required should be left alone');
    }

    public function testUsesTheConfiguredParameterName(): void
    {
        $openApi = $this->factory(tokenParameterName: 'super_secret_cookie_name')();

        $properties = self::arrayAt($this->okSchema($openApi, self::LOGIN_PATH), 'properties');

        $this->assertArrayHasKey('super_secret_cookie_name', $properties);
        $this->assertArrayNotHasKey('refresh_token', $properties);
    }

    public function testDocumentsTheExpirationOnlyWhenItIsReturned(): void
    {
        $without = self::arrayAt($this->okSchema($this->factory()(), self::LOGIN_PATH), 'properties');

        $this->assertArrayNotHasKey('refresh_token_expiration', $without);

        $with = self::arrayAt($this->okSchema($this->factory(returnExpiration: true)(), self::LOGIN_PATH), 'properties');

        $this->assertArrayHasKey('refresh_token_expiration', $with);
        $this->assertSame('integer', self::arrayAt($with, 'refresh_token_expiration')['type'] ?? null);
    }

    /**
     * The case a decorator written by hand gets wrong: the token is in a cookie and taken back out
     * of the body, so documenting a `refresh_token` property promises something that never arrives.
     */
    public function testDocumentsNoTokenInTheBodyWhenTheCookieReplacesIt(): void
    {
        $openApi = $this->factory(cookieSettings: ['enabled' => true, 'remove_token_from_body' => true])();

        $login = self::arrayAt($this->okSchema($openApi, self::LOGIN_PATH), 'properties');

        $this->assertArrayNotHasKey('refresh_token', $login);
        $this->assertArrayHasKey('token', $login, 'The JWT is still in the body');

        $refresh = $openApi->getPaths()->getPath(self::CHECK_PATH)?->getPost();

        $this->assertNotNull($refresh);
        $this->assertNull($refresh->getRequestBody(), 'The cookie carries it, so no body is asked for');
        $this->assertStringContainsString('cookie', (string) $refresh->getDescription());
    }

    /**
     * Enabling the cookie without removing the token from the body leaves it in both, so it is
     * still documented.
     */
    public function testKeepsTheTokenInTheBodyWhenTheCookieOnlyAddsToIt(): void
    {
        $openApi = $this->factory(cookieSettings: ['enabled' => true, 'remove_token_from_body' => false])();

        $login = self::arrayAt($this->okSchema($openApi, self::LOGIN_PATH), 'properties');

        $this->assertArrayHasKey('refresh_token', $login);
    }

    /**
     * Lexik's API Platform integration can be off, in which case there is no login endpoint in the
     * specification and there is nothing to complete.
     */
    public function testLeavesTheSpecificationAloneWhenThereIsNoLoginEndpointToComplete(): void
    {
        $openApi = $this->factory(decorated: $this->emptySpecification())();

        $this->assertNull($openApi->getPaths()->getPath(self::LOGIN_PATH));
        $this->assertNotNull($openApi->getPaths()->getPath(self::CHECK_PATH), 'The refresh endpoint is still documented');
    }

    /**
     * A path of somebody else's that happens to be a POST is not the login endpoint and is left as
     * it is.
     */
    public function testLeavesOtherPathsAlone(): void
    {
        $paths = $this->emptySpecification()->getPaths();
        $paths->addPath('/api/books', (new PathItem())->withPost(
            (new Operation())->withOperationId('api_books_post')->withResponses([
                Response::HTTP_OK => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string']]]]]],
            ])
        ));

        $openApi = $this->factory(decorated: new OpenApi(new Info('Test', '1.0'), [], $paths))();

        $properties = self::arrayAt($this->okSchema($openApi, '/api/books'), 'properties');

        $this->assertSame(['title'], array_keys($properties));
    }

    /**
     * The OpenAPI models hold the response schemas as plain arrays, so reading one out means
     * checking each step rather than chaining through them.
     *
     * @return array<mixed>
     */
    private function okSchema(OpenApi $openApi, string $path): array
    {
        $operation = $openApi->getPaths()->getPath($path)?->getPost();

        $this->assertNotNull($operation, sprintf('"%s" should be documented', $path));

        return $this->okProperties($operation, false);
    }

    /**
     * @return array<mixed>
     */
    private function okProperties(Operation $operation, bool $onlyProperties = true): array
    {
        $responses = $operation->getResponses() ?? [];
        $ok = self::arrayAt($responses, (string) Response::HTTP_OK);
        $schema = self::arrayAt(self::arrayAt(self::arrayAt($ok, 'content'), 'application/json'), 'schema');

        return $onlyProperties ? self::arrayAt($schema, 'properties') : $schema;
    }

    /**
     * @param array<mixed> $array
     *
     * @return array<mixed>
     */
    private static function arrayAt(array $array, string $key): array
    {
        $value = $array[$key] ?? null;

        return is_array($value) ? $value : [];
    }

    /**
     * @param string[]|null             $checkPaths
     * @param array<string, mixed>|null $cookieSettings
     */
    private function factory(
        ?array $checkPaths = null,
        string $tokenParameterName = 'refresh_token',
        bool $returnExpiration = false,
        ?array $cookieSettings = null,
        ?OpenApi $decorated = null,
    ): OpenApiFactory {
        $inner = $this->createStub(OpenApiFactoryInterface::class);
        $inner->method('__invoke')->willReturn($decorated ?? $this->specificationWithTheLoginEndpoint());

        return new OpenApiFactory(
            $inner,
            $checkPaths ?? [self::CHECK_PATH],
            $tokenParameterName,
            $returnExpiration,
            'refresh_token_expiration',
            $cookieSettings ?? ['enabled' => false],
        );
    }

    /**
     * What Lexik's own factory contributes, reproduced so this test fails if what it hands over
     * stops being what is completed here.
     */
    private function specificationWithTheLoginEndpoint(): OpenApi
    {
        $openApi = $this->emptySpecification();

        $openApi->getPaths()->addPath(self::LOGIN_PATH, (new PathItem())->withPost(
            (new Operation())
                ->withOperationId('login_check_post')
                ->withTags(['Login Check'])
                ->withResponses([
                    Response::HTTP_OK => [
                        'description' => 'User token created',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'token' => ['readOnly' => true, 'type' => 'string', 'nullable' => false],
                                    ],
                                    'required' => ['token'],
                                ],
                            ],
                        ],
                    ],
                ])
        ));

        return $openApi;
    }

    private function emptySpecification(): OpenApi
    {
        return new OpenApi(new Info('Test', '1.0'), [], new Paths());
    }
}
