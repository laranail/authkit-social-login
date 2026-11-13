<?php

declare(strict_types=1);

use Workbench\App\Models\User;
use Laravel\Socialite\Two\User as SocialiteUser;
use Simtabi\Laranail\AuthKit\Social\Models\Social;
use Simtabi\Laranail\AuthKit\Social\Enums\SocialProvider;
use Simtabi\Laranail\AuthKit\Social\Support\SocialConfig;
use Simtabi\Laranail\AuthKit\Social\Actions\ResolveSocialIdentity;

function callbackIdentity(string $id = '123456789'): SocialiteUser
{
    $raw = [
        'id'             => $id,
        'name'           => 'John Doe',
        'nickname'       => 'johndoe',
        'email'          => 'john@example.com',
        'avatar'         => 'https://example.com/avatar.jpg',
        'email_verified' => true,
    ];

    $user = new SocialiteUser;
    $user->setRaw($raw);
    $user->map($raw);
    $user->token = 'mock-token';
    $user->refreshToken = 'mock-refresh-token';
    $user->expiresIn = 3600;

    return $user;
}

function linkIdentityTo(User $owner, string $id = '123456789'): Social
{
    return Social::query()->create([
        'socialable_type' => $owner::class,
        'socialable_id'   => $owner->getAuthIdentifier(),
        'provider'        => SocialProvider::GOOGLE,
        'provider_id'     => $id,
        'name'            => 'John Doe',
        'email'           => 'john@example.com',
    ]);
}

function resolveCallback(string $guard = 'web'): ?Illuminate\Contracts\Auth\Authenticatable
{
    return app(ResolveSocialIdentity::class)->execute(
        provider: SocialProvider::GOOGLE,
        socialUser: callbackIdentity(),
        guard: $guard,
    );
}

/* ------------------------------------------------- never switch the current user */

it('refuses a callback whose identity belongs to another account', function (): void {
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $signedIn = User::factory()->create(['email' => 'signed-in@example.com']);
    $link = linkIdentityTo($owner);

    $this->actingAs($signedIn);

    expect(resolveCallback())->toBeNull()
        ->and($link->fresh()->socialable_id)->toBe($owner->getAuthIdentifier())
        ->and(Social::query()->count())->toBe(1);
});

it('still signs the owner in when nobody is signed in', function (): void {
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    linkIdentityTo($owner);

    $resolved = resolveCallback();

    expect($resolved?->getAuthIdentifier())->toBe($owner->getAuthIdentifier());
});

it('accepts a callback for an identity the signed-in user already owns', function (): void {
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    linkIdentityTo($owner);

    $this->actingAs($owner);

    expect(resolveCallback()?->getAuthIdentifier())->toBe($owner->getAuthIdentifier())
        ->and(Social::query()->count())->toBe(1);
});

/*
 * `auth()->user()` answers for the default guard. On an application whose social flow runs on any
 * other one it would answer null, the branch above would read that as "nobody is signed in", and
 * the switch it exists to prevent would happen anyway.
 */
it('reads the signed-in user through the guard the callback is for', function (): void {
    config()->set('auth.guards.secondary', ['driver' => 'session', 'provider' => 'users']);

    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $signedIn = User::factory()->create(['email' => 'signed-in@example.com']);
    linkIdentityTo($owner);

    // Deliberately not actingAs(..., 'secondary'): that calls Auth::shouldUse(), which moves the
    // DEFAULT guard too, so `auth()->user()` would answer for 'secondary' as well and the
    // distinction under test would disappear. Set the user on that guard alone instead.
    auth()->guard('secondary')->setUser($signedIn);

    expect(auth()->guard('secondary')->user()?->getAuthIdentifier())->toBe($signedIn->getAuthIdentifier())
        ->and(auth()->user())->toBeNull()
        ->and(resolveCallback('secondary'))->toBeNull();
});

/* ------------------------------------------- no linking without explicit consent */

it('does not attach an unlinked identity to whoever is signed in', function (): void {
    $signedIn = User::factory()->create(['email' => 'signed-in@example.com']);

    $this->actingAs($signedIn);

    expect(resolveCallback())->toBeNull()
        ->and(Social::query()->count())->toBe(0);
});

it('ships with implicit linking off', function (): void {
    expect(SocialConfig::get('linking.implicit_on_callback'))->toBeFalse();
});

/*
 * The capability is preserved rather than removed: an application already depending on it keeps
 * it by opting in, while the default stops being unsafe for everyone who is not.
 */
it('attaches an unlinked identity when the deprecated opt-in is enabled', function (): void {
    config()->set(SocialConfig::KEY . '.linking.implicit_on_callback', true);

    $signedIn = User::factory()->create(['email' => 'signed-in@example.com']);

    $this->actingAs($signedIn);

    expect(resolveCallback()?->getAuthIdentifier())->toBe($signedIn->getAuthIdentifier())
        ->and(Social::query()->count())->toBe(1)
        ->and(Social::query()->first()->socialable_id)->toBe($signedIn->getAuthIdentifier());
});

it('still refuses to switch accounts even with the opt-in enabled', function (): void {
    config()->set(SocialConfig::KEY . '.linking.implicit_on_callback', true);

    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $signedIn = User::factory()->create(['email' => 'signed-in@example.com']);
    linkIdentityTo($owner);

    $this->actingAs($signedIn);

    expect(resolveCallback())->toBeNull()
        ->and(Social::query()->count())->toBe(1);
});
