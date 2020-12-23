<?php

namespace spec\Gesdinet\JWTRefreshTokenBundle\Entity;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class RefreshTokenRepositorySpec extends ObjectBehavior
{
    public function let(EntityManagerInterface $em, ClassMetadata $classMetadata)
    {
        $this->beConstructedWith($em, $classMetadata);
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType('Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository');
    }

    public function it_is_a_repository()
    {
        $this->shouldHaveType('Doctrine\ORM\EntityRepository');
    }

    public function it_finds_invalid_tokens($em, QueryBuilder $builder, AbstractQuery $query, RefreshTokenInterface $token)
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
