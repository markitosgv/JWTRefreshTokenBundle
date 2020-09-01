<?php

namespace Gesdinet\JWTRefreshTokenBundle;

/**
 * Events.
 *
 * @author Sherin Bloemendaal <sherin@dactec.nl>
 */
final class Events
{
    /**
     * Dispatched after the token refresh.
     * Hook into this event to listen to successful token refreshes.
     *
     * @Event("Gesdinet\JWTRefreshTokenBundle\Event\RefreshTokenEvent")
     */
    const ON_REFRESH_TOKEN = 'gesdinet.refresh_token';

    /**
     * Dispatched before the refresh token is persisted into the database.
     * Hook into this event to fill the extra fields when using a custom entity.
     *
     * @Event("Gesdinet\JWTRefreshTokenBundle\Event\RefreshTokenCreatedEvent")
     */
    const ON_REFRESH_TOKEN_CREATED = 'gesdinet.refresh_token_created';
}
