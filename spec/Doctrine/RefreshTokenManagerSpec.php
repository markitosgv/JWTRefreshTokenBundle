<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Doctrine;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use PhpSpec\ObjectBehavior;

class RefreshTokenManagerSpec extends ObjectBehavior
{
    const REFRESH_TOKEN_ENTITY_CLASS = RefreshToken::class;

    public function let(ObjectManager $om, ClassMetadata $class, RefreshToken $entity, RefreshTokenRepository $repository)
    {
        $entity->setUsername('test');
        $entity->setRefreshToken('test_token');

        $om->getRepository(static::REFRESH_TOKEN_ENTITY_CLASS)->willReturn($repository);
        $om->getClassMetadata(static::REFRESH_TOKEN_ENTITY_CLASS)->willReturn($class);
        $class->getName()->willReturn(static::REFRESH_TOKEN_ENTITY_CLASS);

        $this->beConstructedWith($om, static::REFRESH_TOKEN_ENTITY_CLASS);
    }

    public function it_is_a_refresh_manager()
    {
        $this->shouldImplement(RefreshTokenManagerInterface::class);
    }

    public function it_creates_a_token($repository, $refreshToken)
    {
        $this->create()->shouldReturnAnInstanceOf(static::REFRESH_TOKEN_ENTITY_CLASS);
    }

    public function it_retrieves_a_token_from_storage(RefreshTokenRepository $repository, RefreshTokenInterface $refreshToken)
    {
        $token = 'token';

        $repository->findOneBy(['refreshToken' => $token])->willReturn($refreshToken);

        $this->get($token)->shouldReturn($refreshToken);
    }

    public function it_returns_null_when_the_token_does_not_exist_in_storage(RefreshTokenRepository $repository)
    {
        $token = 'token';

        $repository->findOneBy(['refreshToken' => $token])->willReturn(null);

        $this->get($token)->shouldReturn(null);
    }

    public function it_retrieves_the_last_token_for_a_user_from_storage(RefreshTokenRepository $repository, RefreshTokenInterface $refreshToken)
    {
        $username = 'test';
        $repository->findOneBy(['username' => $username], ['valid' => 'DESC'])->willReturn($refreshToken);

        $this->getLastFromUsername($username)->shouldReturn($refreshToken);
    }

    public function it_returns_null_when_a_user_does_not_have_a_token_in_storage(RefreshTokenRepository $repository)
    {
        $username = 'test';
        $repository->findOneBy(['username' => $username], ['valid' => 'DESC'])->willReturn(null);

        $this->getLastFromUsername($username)->shouldReturn(null);
    }

    public function it_saves_the_refresh_token_and_flushes_the_object_manager(RefreshTokenInterface $refreshToken, ObjectManager $om)
    {
        $om->persist($refreshToken)->shouldBeCalled();
        $om->flush()->shouldBeCalled();

        $this->save($refreshToken, true);
    }

    public function it_deletes_the_refresh_token_and_flushes_the_object_manager(RefreshTokenInterface $refreshToken, ObjectManager $om)
    {
        $om->remove($refreshToken)->shouldBeCalled();
        $om->flush()->shouldBeCalled();

        $this->delete($refreshToken, true);
    }

    public function it_revokes_all_invalid_tokens_and_flushes_the_object_manager(ObjectManager $om, RefreshTokenRepository $repository, RefreshTokenInterface $refreshToken)
    {
        $repository->findInvalid(null)->willReturn([$refreshToken]);
        $om->remove($refreshToken)->shouldBeCalled();
        $om->flush()->shouldBeCalled();

        $this->revokeAllInvalid(null, true);
    }

    public function it_provides_the_model_class()
    {
        $this->getClass()->shouldReturn(static::REFRESH_TOKEN_ENTITY_CLASS);
    }
}
