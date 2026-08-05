<?php

declare(strict_types=1);

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Session;

use Doctrine\ORM\Tools\SchemaTool;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Session\SessionLister;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures\Entity\User;
use Gesdinet\JWTRefreshTokenBundle\Tests\Functional\ORMTestCase;
use Gesdinet\JWTRefreshTokenBundle\Tests\Services\UserCreator;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

#[RequiresPhpExtension('pdo_sqlite')]
final class SessionListerTest extends ORMTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        new SchemaTool($this->entityManager)->createSchema([
            $this->entityManager->getClassMetadata(RefreshToken::class),
            $this->entityManager->getClassMetadata(User::class),
        ]);
    }

    public function test_a_user_with_no_tokens_has_no_sessions(): void
    {
        $this->assertSame([], $this->lister()->forUser(UserCreator::create('someone')));
    }

    /**
     * The reason this groups rather than listing tokens. With single_use the same browser leaves a
     * new row on every refresh, and listing them makes one session look like a dozen devices.
     */
    public function test_the_refreshes_of_one_chain_are_one_session(): void
    {
        $this->store('first', 'someone', 600, 'the-chain');
        $this->store('second', 'someone', 900, 'the-chain');
        $this->store('third', 'someone', 1200, 'the-chain');

        $sessions = $this->lister()->forUser(UserCreator::create('someone'));

        $this->assertCount(1, $sessions);
        $this->assertSame('the-chain', $sessions[0]->id);
        $this->assertSame(3, $sessions[0]->tokens);
    }

    public function test_different_chains_are_different_sessions(): void
    {
        $this->store('on-the-laptop', 'someone', 600, 'the-laptop-chain');
        $this->store('on-the-phone', 'someone', 900, 'the-phone-chain');

        $this->assertCount(2, $this->lister()->forUser(UserCreator::create('someone')));
    }

    public function test_leaves_another_users_sessions_out(): void
    {
        $this->store('mine', 'someone', 600, 'my-chain');
        $this->store('somebody-elses', 'somebody-else', 600, 'their-chain');

        $sessions = $this->lister()->forUser(UserCreator::create('someone'));

        $this->assertCount(1, $sessions);
        $this->assertSame('my-chain', $sessions[0]->id);
    }

    public function test_shows_the_session_lasting_longest_first(): void
    {
        $this->store('expires-in-an-hour', 'someone', 3600, 'the-short-one');
        $this->store('expires-in-a-week', 'someone', 604800, 'the-long-one');
        $this->store('expires-in-a-day', 'someone', 86400, 'the-middling-one');

        $sessions = $this->lister()->forUser(UserCreator::create('someone'));

        $this->assertSame(
            ['the-long-one', 'the-middling-one', 'the-short-one'],
            array_map(static fn (\Gesdinet\JWTRefreshTokenBundle\Session\Session $session): ?string => $session->id, $sessions)
        );
    }

    /**
     * A session expires when its last token does, not when the first of them did.
     */
    public function test_a_session_lasts_as_long_as_its_newest_token(): void
    {
        $this->store('the-old-one', 'someone', 600, 'the-chain');
        $this->store('the-new-one', 'someone', 604800, 'the-chain');

        $sessions = $this->lister()->forUser(UserCreator::create('someone'));

        $this->assertEqualsWithDelta(time() + 604800, $sessions[0]->expiresAt->getTimestamp(), 5);
    }

    public function test_reports_when_the_chain_itself_runs_out(): void
    {
        $deadline = new \DateTime()->setTimestamp(time() + 86400);

        $token = RefreshToken::createForUserWithTtl('a-token', UserCreator::create('someone'), 600);
        $token->setFamily('a-bounded-chain');
        $token->setFamilyValid($deadline);
        $this->manager()->save($token);

        $sessions = $this->lister()->forUser(UserCreator::create('someone'));

        $this->assertSame($deadline->getTimestamp(), $sessions[0]->endsAt?->getTimestamp());
    }

    public function test_an_unbounded_chain_has_no_end_date(): void
    {
        $this->store('a-token', 'someone', 600, 'a-chain');

        $this->assertNull($this->lister()->forUser(UserCreator::create('someone'))[0]->endsAt);
    }

    /**
     * So a screen can say "this device" rather than inviting somebody to sign themselves out.
     */
    public function test_marks_the_session_the_request_came_from(): void
    {
        $this->store('the-one-in-my-hand', 'someone', 600, 'this-device');
        $this->store('another', 'someone', 600, 'another-device');

        $sessions = $this->lister()->forUser(UserCreator::create('someone'), 'the-one-in-my-hand');

        $current = array_values(array_filter($sessions, static fn (\Gesdinet\JWTRefreshTokenBundle\Session\Session $session): bool => $session->current));

        $this->assertCount(1, $current);
        $this->assertSame('this-device', $current[0]->id);
    }

    public function test_marks_nothing_current_when_the_request_carried_no_token(): void
    {
        $this->store('a-token', 'someone', 600, 'a-chain');

        $this->assertFalse($this->lister()->forUser(UserCreator::create('someone'))[0]->current);
    }

    /**
     * A session a user cannot see is worse than one they cannot end, so tokens stored before chains
     * existed are shown — each on its own, since nothing says they came from one login.
     */
    public function test_shows_tokens_from_before_chains_existed_separately(): void
    {
        $this->manager()->save(RefreshToken::createForUserWithTtl('one-legacy-token', UserCreator::create('someone'), 600));
        $this->manager()->save(RefreshToken::createForUserWithTtl('another-legacy-token', UserCreator::create('someone'), 900));

        $sessions = $this->lister()->forUser(UserCreator::create('someone'));

        $this->assertCount(2, $sessions);
        $this->assertSame([null, null], array_map(static fn (\Gesdinet\JWTRefreshTokenBundle\Session\Session $session): ?string => $session->id, $sessions));
    }

    public function test_ending_a_session_revokes_every_token_of_that_chain(): void
    {
        $this->store('first', 'someone', 600, 'the-doomed-chain');
        $this->store('second', 'someone', 600, 'the-doomed-chain');
        $this->store('elsewhere', 'someone', 600, 'another-chain');

        $this->assertSame(2, $this->lister()->end(UserCreator::create('someone'), 'the-doomed-chain'));

        $this->entityManager->clear();

        $this->assertNull($this->manager()->get('first'));
        $this->assertNotNull($this->manager()->get('elsewhere'), 'Their other session is untouched');
    }

    /**
     * The check that stops this being a way to sign strangers out. A chain is addressed by an
     * identifier the client hands back, and a session list is exactly where one is handed out.
     */
    public function test_refuses_to_end_a_session_belonging_to_somebody_else(): void
    {
        $this->store('theirs', 'somebody-else', 600, 'their-chain');

        $this->assertSame(0, $this->lister()->end(UserCreator::create('someone'), 'their-chain'));

        $this->entityManager->clear();

        $this->assertNotNull($this->manager()->get('theirs'), 'Their session must survive');
    }

    /**
     * The same answer as a session that is not theirs, so the call cannot be used to find out which
     * chains exist.
     */
    public function test_ending_a_session_that_does_not_exist_reports_nothing(): void
    {
        $this->store('mine', 'someone', 600, 'my-chain');

        $this->assertSame(0, $this->lister()->end(UserCreator::create('someone'), 'a-chain-nobody-has'));
    }

    private function store(string $token, string $username, int $ttl, string $family): void
    {
        $refreshToken = RefreshToken::createForUserWithTtl($token, UserCreator::create($username), $ttl);
        $refreshToken->setFamily($family);

        $this->manager()->save($refreshToken);
    }

    private function manager(): RefreshTokenManager
    {
        return new RefreshTokenManager($this->entityManager, RefreshToken::class, RefreshTokenManagerInterface::DEFAULT_BATCH_SIZE);
    }

    private function lister(): SessionLister
    {
        return new SessionLister($this->manager());
    }
}
