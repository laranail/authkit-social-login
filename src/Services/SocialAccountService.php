<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Social\Services;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\AuthKit\Social\Models\Social;
use Simtabi\Laranail\AuthKit\Contracts\SocialIdentityProviderInterface;

/**
 * Reads and removes the social accounts linked to a user.
 *
 * The rule that matters lives here rather than in a controller, because removing a credential is
 * the one social operation that can lock someone out permanently. An account created through social
 * sign-in has no password — `CreateNewUser` only ever runs for the local flow — so for such a user
 * the linked provider *is* the entire means of signing in. Unlinking it leaves an account nobody can
 * reach, including support.
 */
class SocialAccountService
{
    /**
     * Every provider currently linked to a user.
     *
     * @return Collection<int, Social>
     */
    public function forUser(Authenticatable $user): Collection
    {
        return Social::query()
            ->where('socialable_type', $user::class)
            ->where('socialable_id', $user->getAuthIdentifier())
            ->orderBy('provider')
            ->get();
    }

    /**
     * Whether removing this provider would leave the user able to sign in.
     *
     * Exposed separately from unlink() so a UI can disable the control and explain why, rather than
     * offering an action that then refuses.
     */
    public function canUnlink(Authenticatable $user, SocialIdentityProviderInterface $provider): bool
    {
        $remaining = $this->forUser($user)
            ->reject(fn (Social $social): bool => $this->slugOf($social) === $provider->slug())
            ->count();

        if ($remaining > 0) {
            return true;
        }

        // Opt-in, and off by default. An application that records whether a password was actually
        // chosen -- rather than generated during social provisioning -- can answer the question this
        // package cannot, and set this to true.
        return (bool) config(key: 'laranail.authkit-social-login.unlink.trust_password_column', default: false)
            && $this->hasPassword($user);
    }

    /**
     * Remove a linked provider, or return false when doing so would lock the user out.
     *
     * Returns false rather than throwing because the caller has to render something either way, and
     * "you cannot remove your only way to sign in" is an ordinary answer to give a user, not an
     * exceptional one.
     */
    public function unlink(Authenticatable $user, SocialIdentityProviderInterface $provider): bool
    {
        if (! $this->canUnlink(user: $user, provider: $provider)) {
            return false;
        }

        Social::query()
            ->where('socialable_type', $user::class)
            ->where('socialable_id', $user->getAuthIdentifier())
            ->where('provider', $provider->slug())
            ->delete();

        return true;
    }

    /**
     * The stored provider slug, whether the model handed back an enum or a string.
     *
     * Social casts `provider` to SocialProvider, so a comparison against a slug string is silently
     * always false -- which made every "is this the last link" check answer no. A provider
     * contributed through the registry has no enum case at all, so the value comes back as a plain
     * string; both shapes have to be handled here.
     */
    private function slugOf(Social $social): string
    {
        $provider = $social->getAttribute('provider');

        return $provider instanceof SocialIdentityProviderInterface
            ? $provider->slug()
            : (string) $provider;
    }

    /**
     * Whether the password column holds anything at all.
     *
     * Consulted only when an application has opted in, because on its own it does not answer the
     * question that matters: a social-provisioned account carries a random hash and would pass this
     * check while being unable to sign in with a password.
     */
    private function hasPassword(Authenticatable $user): bool
    {
        if (! $user instanceof Model) {
            return false;
        }

        $password = $user->getAttribute('password');

        return is_string($password) && $password !== '';
    }
}
