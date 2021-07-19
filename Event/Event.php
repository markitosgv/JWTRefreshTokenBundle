<?php

namespace Gesdinet\JWTRefreshTokenBundle\Event;

use Symfony\Component\EventDispatcher\Event as BaseEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\Event as ContractsBaseEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

if (is_subclass_of(EventDispatcher::class, EventDispatcherInterface::class)) {
    /**
     * Internal event class supporting symfony/event-dispatcher >=4.3.
     *
     * @internal
     */
    class Event extends ContractsBaseEvent
    {
    }
} else {
    /**
     * Internal event class supporting symfony/event-dispatcher <4.3.
     *
     * @internal
     */
    class Event extends BaseEvent
    {
    }
}
