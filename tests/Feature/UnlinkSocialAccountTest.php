<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Workbench\App\Models\User;
use Illuminate\Support\Facades\Hash;
use Simtabi\Laranail\AuthKit\Social\Models\Social;
use Simtabi\Laranail\AuthKit\Social\Enums\SocialProvider;
use Simtabi\Laranail\AuthKit\Social\Services\SocialAccountService;
use Simtabi\Laranail\AuthKit\Social\Contracts\UnlinkSocialAccountInterface;

function linkProvider(User $user, string $provider, string $id = 'pid'): Social
{
    return Social::query()->create([
        'socialable_type' => $user::class,
        'socialable_id'   => $user->getKey(),
        'provider'        => $provider,
        'provider_id'     => $id,
        'email'           => $user->email,
    ]);
}

it('refuses to remove the only link, even though the password column is populated', function (): void {
    // This is the trap. ResolveSocialIdentity provisions a social account with
    // Hash::make(Str::random(32)) -- a password the user has never seen and can never type -- and
    // Laravel's schema makes the column NOT NULL, so it is populated for exactly the accounts most
    // at risk. A rule that read it would wave through every unlink that matters.
    $user = User::factory()->create(['password' => Hash::make(Str::random(32))]);
    linkProvider($user, 'google');

    expect(app(UnlinkSocialAccountInterface::class)->execute($user, SocialProvider::GOOGLE))->toBeFalse()
        ->and(Social::query()->count())->toBe(1);
});

it('unlinks a provider when another one remains', function (): void {
    $user = User::factory()->create();
    linkProvider($user, 'google', 'g-1');
    linkProvider($user, 'x', 'x-1');

    expect(app(UnlinkSocialAccountInterface::class)->execute($user, SocialProvider::GOOGLE))->toBeTrue()
        ->and(Social::query()->pluck('provider')->map->slug()->all())->toBe(['x']);
});

it('lets an application that knows better opt in', function (): void {
    // An application recording whether a password was actually chosen can answer the question this
    // package cannot.
    config()->set('laranail.authkit-social-login.unlink.trust_password_column', true);
    $user = User::factory()->create();
    linkProvider($user, 'google');

    expect(app(UnlinkSocialAccountInterface::class)->execute($user, SocialProvider::GOOGLE))->toBeTrue()
        ->and(Social::query()->count())->toBe(0);
});

it('answers canUnlink() ahead of the attempt, so a UI can explain rather than refuse', function (): void {
    $onlyLink = User::factory()->create();
    linkProvider($onlyLink, 'google');

    $twoLinks = User::factory()->create();
    linkProvider($twoLinks, 'google', 'g-2');
    linkProvider($twoLinks, 'x', 'x-2');

    $accounts = app(SocialAccountService::class);

    expect($accounts->canUnlink($onlyLink, SocialProvider::GOOGLE))->toBeFalse()
        ->and($accounts->canUnlink($twoLinks, SocialProvider::GOOGLE))->toBeTrue();
});

it('never touches another user’s links', function (): void {
    // Two links on the user, or the last-credential rule would refuse and prove nothing about
    // isolation.
    $user = User::factory()->create();
    $other = User::factory()->create();
    linkProvider($user, 'google', 'mine');
    linkProvider($user, 'x', 'mine-x');
    linkProvider($other, 'google', 'theirs');

    app(UnlinkSocialAccountInterface::class)->execute($user, SocialProvider::GOOGLE);

    expect(Social::query()->orderBy('provider_id')->pluck('provider_id')->all())
        ->toBe(['mine-x', 'theirs']);
});

it('lists only the requesting user’s linked providers', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    linkProvider($user, 'google', 'mine');
    linkProvider($other, 'x', 'theirs');

    expect(app(SocialAccountService::class)->forUser($user)->pluck('provider_id')->all())
        ->toBe(['mine']);
});
