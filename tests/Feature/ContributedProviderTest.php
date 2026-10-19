<?php

declare(strict_types=1);

use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use Simtabi\Laranail\AuthKit\Support\IdentityProvider;
use Simtabi\Laranail\Package\Tools\Testing\TestCredentials;
use Simtabi\Laranail\AuthKit\Social\Services\PayPalSocialProvider;
use Simtabi\Laranail\AuthKit\Contracts\IdentityProviderRegistryInterface;

it('binds the Socialite driver of a provider that supplies one', function (): void {
    // Registering with the registry and binding the Socialite driver used to be separate steps, and
    // nothing warned when the second was forgotten: the slug resolved and then Socialite threw at
    // the callback. One registration now does both.
    app(IdentityProviderRegistryInterface::class)->register(new IdentityProvider(
        slug: 'acme',
        label: 'Acme',
        assertsEmailVerified: true,
        driverClass: PayPalSocialProvider::class,
    ));

    config()->set('services.acme', [
        'client_id'     => 'id',
        'client_secret' => TestCredentials::secret('acme'),
        'redirect'      => 'https://example.test/cb',
    ]);

    // SocialiteWasCalled fires once, inside app->booted(). A sub-package registering during its
    // own boot() is seen; a test registering afterwards has to fire the event itself.
    event(app(SocialiteWasCalled::class));

    expect(Socialite::driver('acme'))->toBeInstanceOf(PayPalSocialProvider::class);
});

it('binds under the provider’s driver key, not its slug', function (): void {
    // The two differ whenever Socialite's key is not ours -- the reason LinkedIn ships broken
    // without this distinction.
    app(IdentityProviderRegistryInterface::class)->register(new IdentityProvider(
        slug: 'acme-sso',
        label: 'Acme SSO',
        assertsEmailVerified: true,
        driver: 'acme-oidc',
        driverClass: PayPalSocialProvider::class,
    ));

    config()->set('services.acme-oidc', [
        'client_id'     => 'id',
        'client_secret' => TestCredentials::secret('acme'),
        'redirect'      => 'https://example.test/cb',
    ]);

    event(app(SocialiteWasCalled::class));

    expect(Socialite::driver('acme-oidc'))->toBeInstanceOf(PayPalSocialProvider::class);
});

it('leaves a provider without a driver class to whatever Socialite already knows', function (): void {
    app(IdentityProviderRegistryInterface::class)->register(new IdentityProvider(
        slug: 'google-ish',
        label: 'Google-ish',
        assertsEmailVerified: true,
        driver: 'google',
    ));

    expect(app(IdentityProviderRegistryInterface::class)->get('google-ish')?->driver())->toBe('google')
        ->and(app(IdentityProviderRegistryInterface::class)->get('google-ish')?->driverClass)->toBeNull();
});
