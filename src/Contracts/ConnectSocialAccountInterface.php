<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Social\Contracts;

use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\AuthKit\Social\Models\Social;
use Simtabi\Laranail\AuthKit\Social\Support\SocialConnectResult;

/**
 * Adding a provider to an account that is already signed in.
 *
 * Deliberately not the sign-in path. Nothing here authenticates anybody and nothing here reads an
 * email address: whether a link is allowed is decided by AuthKit's IdentityLinkingInterface, which
 * has nowhere to put one.
 */
interface ConnectSocialAccountInterface
{
    /**
     * Begin a connect attempt and return where to send the user.
     *
     * Refused unless a credential was confirmed recently. `$callbackUrl` is the connect callback,
     * which is a different endpoint from the sign-in callback and must be the `redirect_uri` the
     * provider sees, or the token exchange on the way back will not match.
     *
     * @throws \Simtabi\Laranail\AuthKit\Exceptions\IdentityLinkException
     */
    public function start(Request $request, Authenticatable $user, string $guard, string $callbackUrl): SocialConnectResult;

    /**
     * Finish the attempt and write the link.
     *
     * Refuses a replayed or mismatched attempt, and an identity owned by another account. Returns
     * the existing row unchanged when this user already owns the identity, so a double submission
     * is harmless. Signs nobody in, ever.
     *
     * @throws \Simtabi\Laranail\AuthKit\Exceptions\IdentityLinkException
     */
    public function complete(Request $request, Authenticatable $user, string $guard, string $callbackUrl, bool $stateless = false): Social;
}
