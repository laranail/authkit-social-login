<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Social\Actions;

use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\AuthKit\Social\Models\Social;
use Simtabi\Laranail\AuthKit\Social\Support\SocialConfig;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Simtabi\Laranail\AuthKit\Contracts\IdentityLinkingInterface;
use Simtabi\Laranail\AuthKit\Social\Support\SocialConnectResult;
use Simtabi\Laranail\AuthKit\Social\Support\ResolvesIdentityProvider;
use Simtabi\Laranail\AuthKit\Social\Contracts\ConnectSocialAccountInterface;
use Simtabi\Laranail\AuthKit\Social\Contracts\CreateSocialAccountActionInterface;

/**
 * Connecting a provider to the account you are already signed into.
 *
 * Every decision about whether the link is allowed belongs to AuthKit's
 * `IdentityLinkingInterface`; this owns the OAuth round trip and the row. That split is the point:
 * the rules live in one place that cannot sign anyone in and cannot see an email address, and this
 * class cannot quietly grow its own version of them.
 */
class ConnectSocialAccount implements ConnectSocialAccountInterface
{
    use ResolvesIdentityProvider;

    /** Where the web flow parks the token across the redirect. Vendor-scoped: the session is shared. */
    public const string SESSION_KEY = 'laranail.authkit-social-login.connect_token';

    /** The input a token-authenticated client hands the token back on. */
    public const string TOKEN_INPUT = 'link_token';

    public function __construct(
        private SocialiteFactory $socialite,
        private IdentityLinkingInterface $linking,
        private CreateSocialAccountActionInterface $createSocialAccount,
    ) {}

    public function start(Request $request, Authenticatable $user, string $guard, string $callbackUrl): SocialConnectResult
    {
        $provider = $this->resolveProvider(request: $request);

        // Issued before the redirect, so a refusal -- no recent credential -- happens here rather
        // than after the user has been round the provider and back.
        $token = $this->linking->issueIntent(
            request: $request,
            user: $user,
            guard: $guard,
            provider: $provider->slug(),
        );

        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, $token);
        }

        $driver = $this->configure($this->socialite->driver($provider->driver()), $provider->slug(), $callbackUrl);

        return new SocialConnectResult(
            url: $driver->redirect()->getTargetUrl(),
            token: $token,
        );
    }

    public function complete(Request $request, Authenticatable $user, string $guard, string $callbackUrl, bool $stateless = false): Social
    {
        $provider = $this->resolveProvider(request: $request);

        /*
         * Spend the intent before talking to the provider.
         *
         * The other order reads as friendlier -- a provider error would not burn the attempt --
         * but it means a replayed callback URL causes a token exchange against the provider every
         * time before anything refuses it. Refusing first costs the user one restart in the rare
         * case the provider errors, and costs an attacker the whole replay.
         */
        $this->linking->consumeIntent(
            request: $request,
            token: $this->token($request),
            user: $user,
            guard: $guard,
            provider: $provider->slug(),
        );

        $driver = $this->configure($this->socialite->driver($provider->driver()), $provider->slug(), $callbackUrl);

        if ($stateless && method_exists($driver, 'stateless')) {
            $driver = $driver->stateless();
        }

        $socialUser = $driver->user();

        $existing = Social::query()
            ->where('provider', $provider->slug())
            ->where('provider_id', $socialUser->getId())
            ->first();

        // The owner lookup lives here because this package owns the table; the rule about what
        // that owner means lives in AuthKit. An identity held by somebody else is refused, and
        // one already held by this user returns unchanged rather than duplicating a row.
        $this->linking->assertLinkable($user, $existing?->socialable);

        if ($existing !== null) {
            return $existing;
        }

        return $this->createSocialAccount->execute(
            authenticatable: $user,
            provider: $provider,
            socialUser: $socialUser,
        );
    }

    /**
     * The token the attempt is being finished with: the session for a browser, the request for a
     * client that has none. Never both -- a session value is not read from input.
     */
    private function token(Request $request): string
    {
        if ($request->hasSession()) {
            return (string) $request->session()->pull(self::SESSION_KEY, '');
        }

        return (string) $request->input(self::TOKEN_INPUT, '');
    }

    /**
     * Apply the provider's configured scopes and parameters, and pin the redirect URI.
     *
     * The connect callback is a different endpoint from the sign-in callback, so the driver's
     * configured `redirect` is wrong for this flow. It has to be set on both legs: the provider
     * records it on the authorize call and checks it again on the token exchange.
     */
    private function configure(mixed $driver, string $slug, string $callbackUrl): mixed
    {
        if (method_exists($driver, 'redirectUrl')) {
            $driver = $driver->redirectUrl($callbackUrl);
        }

        $settings = SocialConfig::get($slug, []);

        if (is_array($settings)) {
            if (! empty($settings['scopes']) && is_array($settings['scopes']) && method_exists($driver, 'scopes')) {
                $driver->scopes($settings['scopes']);
            }

            if (! empty($settings['with']) && is_array($settings['with']) && method_exists($driver, 'with')) {
                $driver->with($settings['with']);
            }
        }

        return $driver;
    }
}
