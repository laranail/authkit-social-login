<?php

declare(strict_types=1);

use Workbench\App\Models\User;
use Laravel\Socialite\Two\User as SocialiteUser;
use Simtabi\Laranail\AuthKit\Social\Models\Social;
use Simtabi\Laranail\AuthKit\Social\Enums\SocialProvider;
use Simtabi\Laranail\Package\Tools\Testing\TestCredentials;
use Simtabi\Laranail\AuthKit\Social\Actions\ResolveSocialIdentity;

function socialiteUser(array $overrides = []): SocialiteUser
{
    $raw = array_merge([
        'id'             => '123456789',
        'name'           => 'John Doe',
        'nickname'       => 'johndoe',
        'email'          => 'john@example.com',
        'avatar'         => 'https://example.com/avatar.jpg',
        'email_verified' => true,
    ], $overrides);

    $user = new SocialiteUser;
    $user->setRaw($raw);
    $user->map($raw);
    $user->token = 'mock-token';
    $user->refreshToken = 'mock-refresh-token';
    $user->expiresIn = 3600;

    return $user;
}

function socialiteUserWithoutVerification(): SocialiteUser
{
    $raw = [
        'id'       => '123456789',
        'name'     => 'John Doe',
        'nickname' => 'johndoe',
        'email'    => 'john@example.com',
        'avatar'   => 'https://example.com/avatar.jpg',
    ];

    $user = new SocialiteUser;
    $user->setRaw($raw);
    $user->map($raw);
    $user->token = 'mock-token';
    $user->refreshToken = 'mock-refresh-token';
    $user->expiresIn = 3600;

    return $user;
}

it('returns existing socialable when social record matches', function (): void {
    $existingUser = User::factory()->create(['email' => 'john@example.com']);
    Social::query()->create([
        'socialable_type' => get_class($existingUser),
        'socialable_id'   => $existingUser->getAuthIdentifier(),
        'provider'        => 'google',
        'provider_id'     => '123456789',
        'name'            => 'John Doe',
        'email'           => 'john@example.com',
        'token'           => TestCredentials::secret('stale'),
        'refresh_token'   => TestCredentials::secret('refresh'),
    ]);

    $user = app(ResolveSocialIdentity::class)->execute(
        provider: SocialProvider::GOOGLE,
        socialUser: socialiteUser(),
        guard: 'web',
    );

    expect($user->getAuthIdentifier())->toBe($existingUser->getAuthIdentifier());
});

it('updates tokens on existing social record', function (): void {
    $existingUser = User::factory()->create(['email' => 'john@example.com']);
    Social::query()->create([
        'socialable_type' => get_class($existingUser),
        'socialable_id'   => $existingUser->getAuthIdentifier(),
        'provider'        => 'google',
        'provider_id'     => '123456789',
        'name'            => 'John Doe',
        'email'           => 'john@example.com',
        'token'           => TestCredentials::secret('stale'),
        'refresh_token'   => TestCredentials::secret('refresh'),
    ]);

    app(ResolveSocialIdentity::class)->execute(
        provider: SocialProvider::GOOGLE,
        socialUser: socialiteUser(),
        guard: 'web',
    );

    $social = Social::first();
    expect($social->token)->not->toBe(TestCredentials::secret('stale'));
});

it('auto-links by email only when provider asserts email is verified', function (): void {
    $existingUser = User::factory()->create(['email' => 'john@example.com']);

    $user = app(ResolveSocialIdentity::class)->execute(
        provider: SocialProvider::GOOGLE,
        socialUser: socialiteUser(['email_verified' => true]),
        guard: 'web',
    );

    expect($user->getAuthIdentifier())->toBe($existingUser->getAuthIdentifier())
        ->and(Social::query()->count())->toBe(1);
});

it('does not auto-link by email when provider has not verified it', function (): void {
    User::factory()->create(['email' => 'john@example.com']);

    $user = app(ResolveSocialIdentity::class)->execute(
        provider: SocialProvider::GOOGLE,
        socialUser: socialiteUser(['email_verified' => false]),
        guard: 'web',
    );

    expect($user)->toBeNull();
});

it('does not create a user when the provider has not verified the email', function (): void {
    $user = app(ResolveSocialIdentity::class)->execute(
        provider: SocialProvider::GOOGLE,
        socialUser: socialiteUser(['email_verified' => false]),
        guard: 'web',
    );

    expect($user)->toBeNull()
        ->and(User::query()->count())->toBe(0)
        ->and(Social::query()->count())->toBe(0);
});

it('does not treat a string false verification claim as verified', function (): void {
    User::factory()->create(['email' => 'john@example.com']);

    $user = app(ResolveSocialIdentity::class)->execute(
        provider: SocialProvider::GOOGLE,
        socialUser: socialiteUser(['email_verified' => 'false']),
        guard: 'web',
    );

    expect($user)->toBeNull()
        ->and(Social::query()->count())->toBe(0);
});

it('accepts LinkedIns documented email verification claim', function (): void {
    $user = app(ResolveSocialIdentity::class)->execute(
        provider: SocialProvider::LINKEDIN,
        socialUser: socialiteUser(['email_verified' => 'true']),
        guard: 'web',
    );

    expect($user)->not->toBeNull()
        ->and($user->email)->toBe('john@example.com');
});

it('does not auto-link when raw user has no verification field', function (): void {
    User::factory()->create(['email' => 'john@example.com']);

    $user = app(ResolveSocialIdentity::class)->execute(
        provider: SocialProvider::GOOGLE,
        socialUser: socialiteUserWithoutVerification(),
        guard: 'web',
    );

    expect($user)->toBeNull();
});

it('returns null when socialite user has no email and no social record exists', function (): void {
    $user = app(ResolveSocialIdentity::class)->execute(
        provider: SocialProvider::GOOGLE,
        socialUser: socialiteUser(['email' => null]),
        guard: 'web',
    );

    expect($user)->toBeNull();
});

it('creates a new user when no match and email is verified', function (): void {
    $user = app(ResolveSocialIdentity::class)->execute(
        provider: SocialProvider::GOOGLE,
        socialUser: socialiteUser(),
        guard: 'web',
    );

    expect($user)->not->toBeNull()
        ->and($user->email)->toBe('john@example.com')
        ->and(Social::query()->count())->toBe(1)
        ->and(Social::first()->provider->value)->toBe('google');
});

it('normalizes and verifies email addresses trusted from a provider', function (): void {
    $user = app(ResolveSocialIdentity::class)->execute(
        provider: SocialProvider::GOOGLE,
        socialUser: socialiteUser(['email' => 'John@Example.COM']),
        guard: 'web',
    );

    expect($user->email)->toBe('john@example.com')
        ->and($user->email_verified_at)->not->toBeNull();
});

it('links social account to authenticated user', function (): void {
    $authUser = User::factory()->create();
    $this->actingAs($authUser);

    $user = app(ResolveSocialIdentity::class)->execute(
        provider: SocialProvider::GOOGLE,
        socialUser: socialiteUser(),
        guard: 'web',
    );

    expect($user->getAuthIdentifier())->toBe($authUser->getAuthIdentifier())
        ->and(Social::query()->count())->toBe(1)
        ->and(Social::first()->socialable_id)->toBe($authUser->getAuthIdentifier());
});

it('accepts the confirmed address X returns as its verification claim', function (): void {
    $user = app(ResolveSocialIdentity::class)->execute(
        provider: SocialProvider::X,
        socialUser: socialiteUser(['confirmed_email' => 'john@example.com']),
        guard: 'web',
    );

    expect($user)->not->toBeNull()
        ->and($user->email)->toBe('john@example.com');
});

it('reads each provider’s own claim, so X is unverified without confirmed_email', function (): void {
    User::factory()->create(['email' => 'john@example.com']);

    // The shared payload carries email_verified: true. X does not use that claim -- it
    // omits confirmed_email altogether when the address is unconfirmed -- so trusting
    // the wrong key here would hand an account over on a claim X never made.
    $user = app(ResolveSocialIdentity::class)->execute(
        provider: SocialProvider::X,
        socialUser: socialiteUser(),
        guard: 'web',
    );

    expect($user)->toBeNull()
        ->and(Social::query()->count())->toBe(0);
});

it('accepts the string email_verified Apple sends rather than a real boolean', function (): void {
    $existingUser = User::factory()->create(['email' => 'john@example.com']);

    $user = app(ResolveSocialIdentity::class)->execute(
        provider: SocialProvider::APPLE,
        socialUser: socialiteUser(['email_verified' => 'true']),
        guard: 'web',
    );

    expect($user->getAuthIdentifier())->toBe($existingUser->getAuthIdentifier())
        ->and(Social::query()->count())->toBe(1);
});

it('does not link an Apple identity whose email_verified is the string false', function (): void {
    User::factory()->create(['email' => 'john@example.com']);

    $user = app(ResolveSocialIdentity::class)->execute(
        provider: SocialProvider::APPLE,
        socialUser: socialiteUser(['email_verified' => 'false']),
        guard: 'web',
    );

    expect($user)->toBeNull()
        ->and(Social::query()->count())->toBe(0);
});
