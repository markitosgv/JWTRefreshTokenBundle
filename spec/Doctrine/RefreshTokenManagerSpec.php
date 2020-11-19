<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Doctrine;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository;
use PhpSpec\ObjectBehavior;

class RefreshTokenManagerSpec extends ObjectBehavior
{
    const refresh_token_entity_class = 'Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken';

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
        $this->shouldHaveType('Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenManager');
    }

    public function it_gets_token($repository, $refreshToken)
    {
        $repository->findOneBy(['refreshToken' => $refreshToken])->shouldBeCalled();

        $this->get($refreshToken);
    }

    public function it_gets_last_token_from_user($repository, $entity)
    {
        $username = 'test';
        $repository->findOneBy(['username' => $username], ['valid' => 'DESC'])->shouldBeCalled()->willReturn($entity);

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
        $repository->findInvalid(null)->shouldBeCalled()->willReturn([$entity]);
        $om->remove($entity)->shouldBeCalled();
        $om->flush()->shouldBeCalled();

        $this->revokeAllInvalid(null, true);
    }

    public function it_has_class()
    {
        $this->getClass()->shouldBe(static::refresh_token_entity_class);
    }
}
