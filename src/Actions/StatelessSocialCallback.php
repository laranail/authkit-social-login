<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Social\Actions;

use GuzzleHttp\Exception\ClientException;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Socialite\Two\InvalidStateException;
use Simtabi\Laranail\AuthKit\Support\TokenResult;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Simtabi\Laranail\AuthKit\Contracts\IssueTokenForUserInterface;
use Simtabi\Laranail\AuthKit\Contracts\SocialIdentityProviderInterface;
use Simtabi\Laranail\AuthKit\Social\Contracts\ResolveSocialIdentityInterface;
use Simtabi\Laranail\AuthKit\Social\Contracts\StatelessSocialCallbackInterface;

/**
 * Completes a social sign-in for a client with no session, and returns an API token.
 *
 * ## Why this takes an authorization code and not an access token
 *
 * The obvious shape for native social login is "the app does the OAuth dance with the provider's
 * SDK, posts the resulting access token, and the server exchanges it for a user" via Socialite's
 * `userFromToken()`. That shape is **not offered here, deliberately**, because it cannot be made
 * safe with what the providers give us:
 *
 *  - `userFromToken()` calls the provider's userinfo endpoint with the token. The response carries
 *    the user's identity and **no `aud` claim**, so there is nothing in it to compare against our
 *    own client id.
 *  - A token is therefore accepted purely because the provider says it is valid — including a token
 *    minted for a *different* application by the same provider. Anyone able to obtain one for their
 *    own app can present it here and be signed in as that provider's user. This is the standard
 *    token-substitution attack on native social login.
 *  - Even Apple's identity token, which is a real JWT, is not audience-checked: the community
 *    provider constrains issuer, signature and expiry (`IssuedBy`, `SignedWith`, `LooseValidAt`)
 *    and never adds `PermittedFor`.
 *
 * The authorization-code flow has no such hole. **We** perform the exchange, with our own client id
 * and secret, against the provider's token endpoint. A code issued to a different application fails
 * that exchange, because the credentials do not match the code. The client never handles a token we
 * then have to trust.
 *
 * `stateless()` only removes the session-based CSRF state check, which a client with no cookie
 * session cannot participate in. It does not weaken the exchange itself.
 */
class StatelessSocialCallback implements StatelessSocialCallbackInterface
{
    public function __construct(
        private readonly SocialiteFactory $socialite,
        private readonly ResolveSocialIdentityInterface $resolver,
        private readonly IssueTokenForUserInterface $issuer,
        private \Illuminate\Contracts\Events\Dispatcher $events,
    ) {}

    public function execute(SocialIdentityProviderInterface $provider, string $guard, ?string $tokenName = null): ?TokenResult
    {
        $driver = $this->socialite->driver($provider->driver());

        // Guard rather than assume: a custom driver that does not implement stateless() would
        // otherwise silently perform the session state check and fail for every API client.
        if (method_exists($driver, 'stateless')) {
            $driver = $driver->stateless();
        }

        try {
            $socialiteUser = $driver->user();
        } catch (InvalidStateException|ClientException) {
            $this->events->dispatch(new \Simtabi\Laranail\AuthKit\Social\Events\SocialAuthenticationFailed($provider->slug(), $guard, 'api'));

            return null;
        }

        $providerId = $socialiteUser->getId();

        if ((! is_string($providerId) && ! is_int($providerId)) || trim((string) $providerId) === '') {
            $this->events->dispatch(new \Simtabi\Laranail\AuthKit\Social\Events\SocialAuthenticationFailed($provider->slug(), $guard, 'api'));

            return null;
        }

        // The same resolver the web flow uses. Verification and account-linking rules are decided
        // in exactly one place, so an API client can never be granted something a browser could not.
        $user = $this->resolver->execute(
            provider: $provider,
            socialUser: $socialiteUser,
            guard: $guard,
        );

        if (! $user instanceof Authenticatable) {
            $this->events->dispatch(new \Simtabi\Laranail\AuthKit\Social\Events\SocialAuthenticationFailed($provider->slug(), $guard, 'api'));

            return null;
        }

        $result = $this->issuer->execute(
            user: $user,
            name: $tokenName ?? 'api-social-' . $provider->slug(),
        );
        $this->events->dispatch(new \Simtabi\Laranail\AuthKit\Social\Events\SocialAuthenticationSucceeded($user::class, (string) $user->getAuthIdentifier(), $provider->slug(), $guard, 'api'));

        return $result;
    }
}
