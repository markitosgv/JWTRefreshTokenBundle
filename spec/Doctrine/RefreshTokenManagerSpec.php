<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Doctrine;

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class RefreshTokenManagerSpec extends ObjectBehavior
{
    const refresh_token_entity_class = 'Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken';
    const refresh_token_repository_class = 'Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository';

    function let(ObjectManager $om, ObjectRepository $repository, ClassMetadata $class, $entity, $customRepository)
    {

        $entity->beADoubleOf(static::refresh_token_entity_class);
        $entity->setRefreshToken('test_token');

//        $customRepository->beADoubleOf(static::refresh_token_repository_class);
//        $customRepository->willExtend('Doctrine\Common\Persistence\ObjectRepository');

        $om->getRepository(static::refresh_token_entity_class)->willReturn($customRepository);
        $om->getClassMetadata(static::refresh_token_entity_class)->willReturn($class);
        $class->getName()->willReturn(static::refresh_token_entity_class);

        $this->beConstructedWith($om, static::refresh_token_entity_class);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenManager');
    }

    function it_gets_token($customRepository, $refreshToken)
    {

        $customRepository->findOneBy(array('refreshToken' => $refreshToken))->shouldBeCalled();

        $this->get($refreshToken);
    }

//    function it_gets_last_token_from_user($repository)
//    {
//        $username = 'test';
//        $repository->findOneBy(array('username' => $username), array('valid' => 'DESC'))->shouldBeCalled();
//
//
//        $this->getLastFromUser($username);
//    }
//
//    function it_saves_refresh_token_and_flush($entity, $om)
//    {
//        $om->persist($entity)->shouldBeCalled();
//        $om->flush()->shouldBeCalled();
//
//        $this->save($entity, true);
//    }
//
//    function it_saves_refresh_token_and_not_flush($entity, $om)
//    {
//        $om->persist($entity)->shouldBeCalled();
//
//        $this->save($entity, false);
//    }
//
//    function it_deletes_refresh_token_and_flush($entity, $om)
//    {
//        $om->remove($entity)->shouldBeCalled();
//        $om->flush()->shouldBeCalled();
//
//        $this->delete($entity, true);
//    }
//
//    function it_deletes_refresh_token_and_not_flush($entity, $om)
//    {
//        $om->remove($entity)->shouldBeCalled();
//
//        $this->delete($entity, false);
//    }
//
//    function it_has_class()
//    {
//        $this->getClass()->shouldBe(static::refresh_token_manager_class);
//    }

}