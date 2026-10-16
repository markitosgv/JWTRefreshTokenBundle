<?php

declare(strict_types=1);

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional;

use Doctrine\ORM\Tools\SchemaTool;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\EventListener\AttachRefreshTokenOnSuccessListener;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGenerator;
use Gesdinet\JWTRefreshTokenBundle\Model\HashedRefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\RequestParameterExtractor;
use Gesdinet\JWTRefreshTokenBundle\Security\Exception\TokenNotFoundException;
use Gesdinet\JWTRefreshTokenBundle\Security\Http\Authenticator\RefreshTokenAuthenticator;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Entity\User;
use Gesdinet\JWTRefreshTokenBundle\Tests\Services\UserCreator;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\HttpUtils;

/**
 * Two refresh requests presenting the same single-use token at the same time.
 *
 * Each request is the authenticator reading the token and then, later, the success listener
 * spending it. The interleaving that matters is the second request running from start to finish
 * in between the two halves of the first: the first then holds a token whose row is gone, and it
 * must not be given a new pair for it.
 */
#[RequiresPhpExtension('pdo_sqlite')]
final class SingleUseRotationRaceTest extends ORMTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        new SchemaTool($this->entityManager)->createSchema([
            $this->entityManager->getClassMetadata(RefreshToken::class),
            $this->entityManager->getClassMetadata(User::class),
        ]);
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function storage(): iterable
    {
        yield 'tokens stored in the clear' => [false];
        yield 'tokens stored hashed' => [true];
    }

    #[DataProvider('storage')]
    public function test_only_one_of_two_racing_requests_is_given_a_new_token(bool $hashed): void
    {
        $manager = $this->manager($hashed);
        $this->storeToken($manager, 'the-shared-token', 'the-chain');

        $first = $this->authenticate($manager, 'the-shared-token');

        $second = $this->authenticate($manager, 'the-shared-token');
        $this->succeed($manager, ...$second);

        try {
            $this->succeed($manager, ...$first);
            $this->fail('The request that lost the race was given a new token');
        } catch (TokenNotFoundException) {
        }

        $families = $this->entityManager->getConnection()->fetchFirstColumn('SELECT family FROM RefreshToken');

        $this->assertSame(['the-chain'], $families, 'Only the winner\'s replacement is left, in the chain it came from');
    }

    #[DataProvider('storage')]
    public function test_a_refresh_nobody_races_carries_on_the_chain(bool $hashed): void
    {
        $manager = $this->manager($hashed);
        $this->storeToken($manager, 'a-lone-token', 'the-chain');

        $this->succeed($manager, ...$this->authenticate($manager, 'a-lone-token'));

        $families = $this->entityManager->getConnection()->fetchFirstColumn('SELECT family FROM RefreshToken');

        $this->assertSame(['the-chain'], $families);
    }

    private function manager(bool $hashed): RefreshTokenManagerInterface
    {
        $manager = new RefreshTokenManager($this->entityManager, RefreshToken::class, RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE);

        return $hashed ? new HashedRefreshTokenManager($manager) : $manager;
    }

    private function storeToken(RefreshTokenManagerInterface $manager, string $value, string $family): void
    {
        $token = RefreshToken::createForUserWithTtl($value, UserCreator::create('someone'), 600);
        $token->setFamily($family);

        $manager->save($token);
    }

    /**
     * The first half of a refresh request: the authenticator reads the token and the security token
     * it builds is put in storage, as Symfony does before calling the success handler.
     *
     * @return array{Request, TokenStorage}
     */
    private function authenticate(RefreshTokenManagerInterface $manager, string $presented): array
    {
        $request = Request::create('/token/refresh', 'POST', ['refresh_token' => $presented]);

        $authenticator = new RefreshTokenAuthenticator(
            $manager,
            new EventDispatcher(),
            new RequestParameterExtractor(),
            new InMemoryUserProvider(['someone' => ['password' => 'password']]),
            $this->createStub(AuthenticationSuccessHandlerInterface::class),
            $this->createStub(AuthenticationFailureHandlerInterface::class),
            ['check_path' => '/token/refresh'],
            new HttpUtils(),
        );

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($authenticator->createToken($authenticator->authenticate($request), 'api'));

        return [$request, $tokenStorage];
    }

    /**
     * The second half: Lexik's success event, on which the token is spent and its replacement issued.
     */
    private function succeed(RefreshTokenManagerInterface $manager, Request $request, TokenStorage $tokenStorage): void
    {
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $listener = new AttachRefreshTokenOnSuccessListener(
            $manager,
            600,
            $requestStack,
            'refresh_token',
            true,
            new RefreshTokenGenerator($manager),
            new RequestParameterExtractor(),
            [],
            tokenStorage: $tokenStorage,
        );

        $user = $tokenStorage->getToken()?->getUser();
        \assert(null !== $user);

        $listener->attachRefreshToken(new AuthenticationSuccessEvent([], $user, new Response()));
    }
}
