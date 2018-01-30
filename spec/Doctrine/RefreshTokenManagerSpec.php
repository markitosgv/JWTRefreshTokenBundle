<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Doctrine;

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Persistence\ObjectManager;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository;
use PhpSpec\ObjectBehavior;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenManager;
use Prophecy\Argument;

class RefreshTokenManagerSpec extends ObjectBehavior
{
    const refresh_token_entity_class = RefreshToken::class;

    public function let(ObjectManager $om, ClassMetadata $class, RefreshToken $entity, RefreshTokenRepository $repository)
    {
        $entity->setUsername('test');
        $entity->setRefreshToken('test_token');

        $om->getRepository(static::refresh_token_entity_class)->willReturn($repository);
        $om->getClassMetadata(static::refresh_token_entity_class)->willReturn($class);
        $class->getName()->willReturn(static::refresh_token_entity_class);

        $this->beConstructedWith($om, static::refresh_token_entity_class);
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType(RefreshTokenManager::class);
    }

    public function it_gets_token(RefreshTokenRepository $repository)
    {
        $refreshToken = Argument::type('string');
        $repository->findOneBy(array('refreshToken' => $refreshToken))->shouldBeCalled();

        $this->get($refreshToken);
    }

    public function it_gets_last_token_from_user(RefreshTokenRepository $repository, RefreshToken $entity)
    {
        $username = Argument::type('string');
        $repository->findOneBy(array('username' => $username))->shouldBeCalled()->willReturn($entity);

        $this->getLastFromUsername($username)->shouldBe($entity);
    }

    public function it_saves_refresh_token_and_flush($entity, $om)
    {
        $om->persist($entity)->shouldBeCalled();
        $om->flush()->shouldBeCalled();

        $this->save($entity, true);
    }

    public function it_deletes_refresh_token_and_flush($entity, $om)
    {
        $om->remove($entity)->shouldBeCalled();
        $om->flush()->shouldBeCalled();

        $this->delete($entity, true);
    }

    public function it_revokes_all_invalid_and_flush($om, $repository, $entity)
    {
        $repository->findInvalid(null)->shouldBeCalled()->willReturn(array($entity));
        $om->remove($entity)->shouldBeCalled();
        $om->flush()->shouldBeCalled();

        $this->revokeAllInvalid(null, true);
    }

    public function it_has_class()
    {
        $this->getClass()->shouldBe(static::refresh_token_entity_class);
    }
}
