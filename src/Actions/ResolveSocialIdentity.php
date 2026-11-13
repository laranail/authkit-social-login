<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Social\Actions;

use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\AuthKit\Social\Models\Social;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Simtabi\Laranail\AuthKit\Support\UserModelResolver;
use Simtabi\Laranail\AuthKit\Social\Support\SocialConfig;
use Simtabi\Laranail\AuthKit\Contracts\SocialIdentityProviderInterface;
use Simtabi\Laranail\AuthKit\Social\Contracts\ResolveSocialIdentityInterface;
use Simtabi\Laranail\AuthKit\Social\Contracts\CreateSocialAccountActionInterface;

class ResolveSocialIdentity implements ResolveSocialIdentityInterface
{
    public function __construct(
        private CreateSocialAccountActionInterface $createSocialAccount,
    ) {}

    public function execute(SocialIdentityProviderInterface $provider, SocialiteUser $socialUser, string $guard): ?Authenticatable
    {
        $social = Social::query()
            ->where('provider', $provider->slug())
            ->where('provider_id', $socialUser->getId())
            ->first();

        // Read the signed-in user through the guard this callback is for. `auth()->user()`
        // answers for the default guard, which on an application using any other one is null --
        // and a null here would read as "nobody is signed in", which is exactly the branch the
        // check below exists to avoid taking.
        $current = auth()->guard($guard)->user();

        if ($social !== null) {
            $owner = $social->socialable;

            // Returning the owner signs them in, so it is only ever right when nobody is signed
            // in already. Someone finishing this flow while authenticated as a different account
            // is refused: the identity is not transferred, and the session is not switched to its
            // owner. Without this, completing a provider callback with somebody else's identity
            // replaced the current session with theirs.
            if ($current !== null && ! $this->isSameAccount($current, $owner)) {
                return null;
            }

            $social->update([
                'token'         => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'expires_at'    => $socialUser->expiresIn
                    ? now()->addSeconds($socialUser->expiresIn)
                    : null,
            ]);

            return $owner;
        }

        $userModel = UserModelResolver::resolve(guard: $guard);

        if ($current !== null) {
            // Being signed in is not consent to add a sign-in method. Deliberate connecting is
            // AuthKit's IdentityLinkingInterface, which asks for a recently confirmed credential
            // and a single-use attempt bound to the user who began it.
            if (! SocialConfig::get('linking.implicit_on_callback', false)) {
                return null;
            }

            $this->createSocialAccount->execute(
                authenticatable: $current,
                provider: $provider,
                socialUser: $socialUser,
            );

            return $current;
        }

        $email = $this->normalizedVerifiedEmail($provider, $socialUser);

        if ($email !== null && ($existingUser = $this->findUserByEmail($userModel, $email)) !== null) {
            $this->createSocialAccount->execute(
                authenticatable: $existingUser,
                provider: $provider,
                socialUser: $socialUser,
            );

            return $existingUser;
        }

        if ($email === null || $this->findUserByEmail($userModel, $email) !== null) {
            return null;
        }

        $user = $this->createUser($userModel, $socialUser, $email);

        $this->createSocialAccount->execute(
            authenticatable: $user,
            provider: $provider,
            socialUser: $socialUser,
        );

        return $user;
    }

    private function normalizedVerifiedEmail(SocialIdentityProviderInterface $provider, SocialiteUser $socialUser): ?string
    {
        $email = $socialUser->getEmail();

        if ($email === null || ! $this->emailIsVerified($provider, $socialUser)) {
            return null;
        }

        return Str::lower($email);
    }

    private function emailIsVerified(SocialIdentityProviderInterface $provider, SocialiteUser $socialUser): bool
    {
        // hasVerifiedEmail() is the whole question: a provider that asserts nothing answers
        // false there whatever the payload claims, whether it is a built-in enum case or one
        // contributed by a sub-package.
        $rawUser = $socialUser instanceof AbstractUser
            ? $socialUser->getRaw()
            : [];

        return is_array($rawUser) && $provider->hasVerifiedEmail($rawUser);
    }

    /**
     * Same account, compared on both the model class and the key.
     *
     * The key alone is not enough: `socialable` is a morph, so id 1 of one model and id 1 of
     * another are different accounts that would otherwise compare equal.
     */
    private function isSameAccount(Authenticatable $user, ?Authenticatable $other): bool
    {
        return $other !== null
            && $user::class === $other::class
            && hash_equals((string) $user->getAuthIdentifier(), (string) $other->getAuthIdentifier());
    }

    private function findUserByEmail(string $userModel, ?string $email): ?Authenticatable
    {
        if ($email === null) {
            return null;
        }

        return $userModel::query()->where('email', $email)->first();
    }

    private function createUser(string $userModel, SocialiteUser $socialUser, string $email): Authenticatable
    {
        /** @var Model&Authenticatable $user */
        $user = new $userModel;
        $user->forceFill([
            'name'              => $socialUser->getName() ?? $socialUser->getNickname() ?? '',
            'email'             => $email,
            'email_verified_at' => now(),
            'password'          => Hash::make(Str::random(32)),
        ]);
        $user->save();

        return $user;
    }
}
