<?php

/*
 * This file is part of the GesdinetJWTRefreshTokenBundle package.
 *
 * (c) Gesdinet <http://www.gesdinet.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gesdinet\JWTRefreshTokenBundle\EventListener;

use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\FamilyAwareRefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RevokeRefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use Gesdinet\JWTRefreshTokenBundle\Security\Http\Authenticator\Token\PostRefreshTokenAuthenticationToken;
use Gesdinet\JWTRefreshTokenBundle\Security\ReuseDetection\SpentRefreshTokenRegistryInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use LogicException;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @internal
 */
final class AttachRefreshTokenOnSuccessListener
{
    /**
     * @var array{enabled: bool, same_site: 'lax'|'none'|'strict', path: string, domain: string|null, http_only: bool, secure: bool, partitioned: bool, remove_token_from_body: bool}
     */
    private array $cookieSettings;

    /**
     * @param array{enabled?: bool, same_site?: 'lax'|'none'|'strict', path?: string, domain?: string|null, http_only?: bool, secure?: bool, partitioned?: bool, remove_token_from_body?: bool} $cookieSettings
     * @param positive-int|null                                                                                                                                                                 $maxTokensPerUser
     *
     * @psalm-mutation-free
     */
    public function __construct(
        private readonly RefreshTokenManagerInterface $refreshTokenManager,
        private readonly int $ttl,
        private readonly RequestStack $requestStack,
        private readonly string $tokenParameterName,
        private readonly bool $singleUse,
        private readonly RefreshTokenGeneratorInterface $refreshTokenGenerator,
        private readonly ExtractorInterface $extractor,
        array $cookieSettings,
        private readonly bool $returnExpiration = false,
        private readonly string $returnExpirationParameterName = 'refresh_token_expiration',
        private readonly bool $singleUseTtlUpdate = true,
        private readonly ?int $maxTokensPerUser = null,
        private readonly ?TokenStorageInterface $tokenStorage = null,
        private readonly ?SpentRefreshTokenRegistryInterface $spentTokens = null,
        private readonly ?int $maxSessionLifetime = null,
        /**
         * @var array<string, array<string, mixed>> options set on a firewall, keyed by its name
         */
        private readonly array $firewallOptions = [],
        private readonly ?FirewallMap $firewallMap = null,
    ) {
        $this->cookieSettings = array_merge([
            'enabled' => false,
            'same_site' => 'lax',
            'path' => '/',
            'domain' => null,
            'http_only' => true,
            'secure' => true,
            'remove_token_from_body' => true,
            'partitioned' => false,
        ], $cookieSettings);
    }

    /**
     * Revokes the user's oldest sessions once they hold more tokens than they are allowed.
     *
     * Called after the new token is stored, so the login that has just succeeded is counted among
     * them and is the newest, which is why a limit of one leaves exactly that one.
     *
     * @param positive-int|null $maxTokensPerUser
     */
    private function enforceTheTokenLimit(UserInterface $user, ?int $maxTokensPerUser): void
    {
        if (null === $maxTokensPerUser) {
            return;
        }

        if (!$this->refreshTokenManager instanceof RevokeRefreshTokenManagerInterface) {
            // Quietly not applying a limit somebody configured would leave them believing their
            // sessions were bounded when they are not
            throw new LogicException(sprintf('The "max_tokens_per_user" option needs a refresh token manager implementing "%s", and "%s" does not.', RevokeRefreshTokenManagerInterface::class, get_debug_type($this->refreshTokenManager)));
        }

        $this->refreshTokenManager->revokeAllButNewestForUser($user, $maxTokensPerUser);
    }

    /**
     * What this request is to be treated with, once the firewall it arrived on has had its say.
     *
     * The awkward part of per-firewall configuration is that this listener runs on Lexik's
     * authentication success event, which is handed a user and some response data and knows nothing
     * about the firewall — and on a login it is Lexik's authenticator that ran, not this bundle's, so
     * there is nothing to carry the name across from. What there is, is the request, and the firewall
     * map turns a request into a firewall.
     *
     * Without the map, or on a request matching no firewall, every value is the bundle's, which is
     * how this behaved before firewalls could say anything.
     *
     * @return array{ttl: int, token_parameter_name: string, single_use: bool, single_use_ttl_update: bool, max_session_lifetime: positive-int|null, max_tokens_per_user: positive-int|null, return_expiration: bool, return_expiration_parameter_name: string}
     */
    private function optionsFor(Request $request): array
    {
        $firewall = $this->firewallMap?->getFirewallConfig($request)?->getName();
        $set = null !== $firewall ? ($this->firewallOptions[$firewall] ?? []) : [];

        /** @var array{ttl: int, token_parameter_name: string, single_use: bool, single_use_ttl_update: bool, max_session_lifetime: positive-int|null, max_tokens_per_user: positive-int|null, return_expiration: bool, return_expiration_parameter_name: string} $options */
        $options = [
            'ttl' => $set['ttl'] ?? $this->ttl,
            'token_parameter_name' => $set['token_parameter_name'] ?? $this->tokenParameterName,
            'single_use' => $set['single_use'] ?? $this->singleUse,
            'single_use_ttl_update' => $set['single_use_ttl_update'] ?? $this->singleUseTtlUpdate,
            'max_session_lifetime' => $set['max_session_lifetime'] ?? $this->maxSessionLifetime,
            'max_tokens_per_user' => $set['max_tokens_per_user'] ?? $this->maxTokensPerUser,
            'return_expiration' => $set['return_expiration'] ?? $this->returnExpiration,
            'return_expiration_parameter_name' => $set['return_expiration_parameter_name'] ?? $this->returnExpirationParameterName,
        ];

        return $options;
    }

    /**
     * The token the authenticator has just read from this very request, when there is one.
     *
     * A refresh goes through the authenticator, which loads the token to authenticate with it, and
     * then through here, which loaded it again: two queries for one request. Symfony puts the
     * authenticated security token in storage before calling the success handler, so it is already
     * to hand.
     *
     * The value is compared rather than trusted on type alone, so a token left in storage by
     * anything other than this request is never the one acted on. That comparison cannot match when
     * the stored value is a hash, which simply means the query happens as it used to.
     */
    private function refreshTokenFor(string $refreshTokenString): ?RefreshTokenInterface
    {
        $token = $this->tokenStorage?->getToken();

        if ($token instanceof PostRefreshTokenAuthenticationToken
            && $token->getRefreshToken()->getRefreshToken() === $refreshTokenString
        ) {
            return $token->getRefreshToken();
        }

        return $this->refreshTokenManager->get($refreshTokenString);
    }

    /**
     * When a chain starting now would run out, or null if chains are not bounded.
     *
     * Only ever called for a chain that is starting. Carrying the value along the chain rather than
     * recomputing it is what makes the ceiling absolute: recomputed on every refresh it would move
     * forward each time, which is the unbounded session it exists to prevent.
     */
    private static function newFamilyDeadline(?int $maxSessionLifetime): ?\DateTimeInterface
    {
        if (null === $maxSessionLifetime) {
            return null;
        }

        // Mutable, like the expiry the model already carries: the mapping that ships is Doctrine's
        // `datetime`, which refuses a DateTimeImmutable outright
        return new \DateTime()->setTimestamp(time() + $maxSessionLifetime);
    }

    /**
     * Brings the token's expiry back to the chain's, when the chain ends first.
     *
     * @psalm-external-mutation-free
     */
    private function capExpiryAt(RefreshTokenInterface $refreshToken, \DateTimeInterface $familyValid): void
    {
        $valid = $refreshToken->getValid();

        if (null === $valid || $valid > $familyValid) {
            $refreshToken->setValid($familyValid);
        }
    }

    /**
     * Seconds left before the token expires, never below zero: a replacement issued for no time at
     * all is the session having run out, not one lasting forever.
     */
    private function remainingTtl(RefreshTokenInterface $refreshToken): int
    {
        $valid = $refreshToken->getValid();

        if (null === $valid) {
            return 0;
        }

        return max(0, $valid->getTimestamp() - time());
    }

    public function attachRefreshToken(AuthenticationSuccessEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return;
        }

        $user = $event->getUser();
        $data = $event->getData();

        // Everything below reads from here rather than from the properties, so that a firewall
        // saying something different is honoured and one saying nothing follows the bundle
        $options = $this->optionsFor($request);

        // Extract refreshToken from the request, treating an empty value as no value at all so the
        // checks below only have to care about null
        $refreshTokenString = $this->extractor->getRefreshToken($request, $options['token_parameter_name']);

        if ('' === $refreshTokenString || '0' === $refreshTokenString) {
            $refreshTokenString = null;
        }

        // How long the token being replaced had left, read before it goes
        $remainingTtl = null;

        // The chain the token being replaced belonged to, likewise read before it goes, along with
        // when that chain itself runs out
        $inheritedFamily = null;
        $inheritedFamilyValid = null;

        // Remove the current refreshToken if it is single-use
        if (null !== $refreshTokenString && true === $options['single_use']) {
            $refreshToken = $this->refreshTokenFor($refreshTokenString);
            $refreshTokenString = null;

            if ($refreshToken instanceof RefreshTokenInterface) {
                $remainingTtl = $this->remainingTtl($refreshToken);

                if ($refreshToken instanceof FamilyAwareRefreshTokenInterface) {
                    $inheritedFamily = $refreshToken->getFamily();
                    $inheritedFamilyValid = $refreshToken->getFamilyValid();
                }

                // Recorded before the row goes, since afterwards there is nothing left to say the
                // token ever existed and a replay of it would look like any other wrong token
                $this->spentTokens?->remember($refreshToken);

                $this->refreshTokenManager->delete($refreshToken);
            }
        }

        // Set or create the refreshTokenString
        $issuedToken = null;

        if (null !== $refreshTokenString) {
            $data[$options['token_parameter_name']] = $refreshTokenString;

            // Only read back when the expiry is going to be used, either in the body or on the
            // cookie
            if ($options['return_expiration'] || $this->cookieSettings['enabled']) {
                $issuedToken = $this->refreshTokenFor($refreshTokenString);
            }

            if ($options['return_expiration']) {
                $data[$options['return_expiration_parameter_name']] = $issuedToken?->getValid()?->getTimestamp() ?? 0;
            }
        } else {
            // Starting the ttl over on every rotation means refreshing can be chained for as long
            // as the user keeps at it, so the replacement can be made to end when the token it
            // replaces would have
            $refreshToken = $this->refreshTokenGenerator->createForUserWithTtl(
                $user,
                $options['single_use_ttl_update'] || null === $remainingTtl ? $options['ttl'] : $remainingTtl
            );

            // A refresh carries on the chain it came from; anything else is the start of one. An
            // unknown token being refreshed also starts one, since there is nothing to carry on.
            if ($refreshToken instanceof FamilyAwareRefreshTokenInterface) {
                $refreshToken->setFamily($inheritedFamily ?? bin2hex(random_bytes(16)));

                $familyValid = $inheritedFamilyValid ?? self::newFamilyDeadline($options['max_session_lifetime']);

                if (null !== $familyValid) {
                    $refreshToken->setFamilyValid($familyValid);

                    // The chain's ceiling is not a ttl of its own: it cuts the token's expiry short
                    // when the chain would end first, which is the whole point of having it
                    $this->capExpiryAt($refreshToken, $familyValid);
                }
            }

            // Read before storing: a manager that hashes the token keeps the hash on the model,
            // since what is stored is all it may ever hand back. This is the only moment the value
            // the client is given exists
            $refreshTokenString = $refreshToken->getRefreshToken();

            $this->refreshTokenManager->save($refreshToken);
            $this->enforceTheTokenLimit($user, $options['max_tokens_per_user']);
            $issuedToken = $refreshToken;
            $data[$options['token_parameter_name']] = $refreshTokenString;

            if ($options['return_expiration']) {
                $data[$options['return_expiration_parameter_name']] = $refreshToken->getValid()?->getTimestamp() ?? 0;
            }
        }

        // Add a response cookie if enabled
        if ($this->cookieSettings['enabled']) {
            $event->getResponse()->headers->setCookie(
                new Cookie(
                    $options['token_parameter_name'],
                    $refreshTokenString,
                    // The cookie goes when the token does, which is not a ttl from now once the
                    // replacement inherits the expiry of the one it replaced
                    $issuedToken?->getValid()?->getTimestamp() ?? time() + $options['ttl'],
                    $this->cookieSettings['path'],
                    $this->cookieSettings['domain'],
                    $this->cookieSettings['secure'],
                    $this->cookieSettings['http_only'],
                    false,
                    $this->cookieSettings['same_site'],
                    $this->cookieSettings['partitioned'],
                )
            );

            // Remove the refreshTokenString from the response body
            if ($this->cookieSettings['remove_token_from_body']) {
                unset($data[$options['token_parameter_name']]);
            }
        }

        // Set response data
        $event->setData($data);
    }
}
