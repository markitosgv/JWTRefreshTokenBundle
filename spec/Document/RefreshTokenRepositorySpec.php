<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Entity;

use Doctrine\MongoDB\Query\Builder;
use Doctrine\MongoDB\Query\Query;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class RefreshTokenRepositorySpec extends ObjectBehavior
{
    public function let(DocumentManager $dm, ClassMetadata $classMetadata)
    {
        $this->beConstructedWith($dm, $classMetadata);
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType('Gesdinet\JWTRefreshTokenBundle\Document\RefreshTokenRepository');
    }

    public function it_is_a_repository()
    {
        $this->shouldHaveType('Doctrine\ODM\MongoDB\DocumentRepository');
    }

    public function it_finds_invalid_tokens($em, Builder $builder, Query $query, RefreshTokenInterface $token)
    {
        $date = new \DateTime();
        $em->createQueryBuilder()->shouldBeCalled()->willReturn($builder);

        $builder->select('u')->shouldBeCalled()->willReturn($builder);
        $builder->from(Argument::any(), 'u', Argument::cetera())->shouldBeCalled()->willReturn($builder);
        $builder->where('u.valid < :datetime')->shouldBeCalled()->willReturn($builder);
        $builder->setParameter(':datetime', $date)->shouldBeCalled()->willReturn($builder);

        $builder->getQuery()->shouldBeCalled()->willReturn($query);
        $query->getResult()->shouldBeCalled()->willReturn([$token]);

        $this->findInvalid($date)->shouldReturn([$token]);
    }
}
