<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Functional\Fixtures;

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AuthenticatorFactoryInterface;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;

/**
 * SecurityExtension keeps the registered factories private, so record them as they are added.
 */
final class RecordingSecurityExtension extends SecurityExtension
{
    /**
     * @var list<AuthenticatorFactoryInterface>
     */
    public array $addedFactories = [];

    public function getAlias(): string
    {
        return 'security';
    }

    public function addAuthenticatorFactory(AuthenticatorFactoryInterface $factory): void
    {
        $this->addedFactories[] = $factory;

        parent::addAuthenticatorFactory($factory);
    }
}
