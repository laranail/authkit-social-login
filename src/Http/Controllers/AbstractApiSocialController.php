<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Social\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Simtabi\Laranail\AuthKit\Social\Support\ResolvesIdentityProvider;
use Simtabi\Laranail\AuthKit\Social\Contracts\StatelessSocialCallbackInterface;

/**
 * Social sign-in for clients with no browser session.
 *
 * Two steps, because the authorization-code flow needs two: the client asks for a URL, opens it in a
 * system browser or web view, and posts back the `code` the provider returns. The alternative --
 * accepting a provider access token the client already holds -- is not offered, for the reason set
 * out on StatelessSocialCallback.
 */
abstract class AbstractApiSocialController
{
    use ResolvesIdentityProvider;

    /** The URL the client should open to begin sign-in. */
    public function redirect(Request $request, SocialiteFactory $socialite): JsonResponse
    {
        $provider = $this->resolveProvider(request: $request);
        $driver = $socialite->driver($provider->driver());

        if (method_exists($driver, 'stateless')) {
            $driver = $driver->stateless();
        }

        return new JsonResponse(data: [
            'status' => 'ok',
            'data'   => ['url' => $driver->redirect()->getTargetUrl()],
        ]);
    }

    /** Completes sign-in from the `code` the provider returned to the client. */
    public function callback(Request $request, StatelessSocialCallbackInterface $callback): JsonResponse
    {
        $request->validate(rules: ['code' => ['required', 'string']]);

        $result = $callback->execute(
            provider: $this->resolveProvider(request: $request),
            guard: $this->guard(),
        );

        if ($result === null) {
            // Deliberately not more specific. Distinguishing "this address is unverified" from
            // "this address belongs to someone else" tells an unauthenticated caller which
            // addresses have accounts.
            return new JsonResponse(data: [
                'status'  => 'error',
                'message' => __('laranail/authkit-social-login::messages.sign_in_failed'),
            ], status: 422);
        }

        return new JsonResponse(data: [
            'status' => 'ok',
            'data'   => ['token' => $result->token],
        ]);
    }

    abstract protected function guard(): string;
}
