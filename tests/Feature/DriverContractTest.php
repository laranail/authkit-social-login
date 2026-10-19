<?php

declare(strict_types=1);

use Laravel\Socialite\Two\XProvider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\LinkedInOpenIdProvider;
use Simtabi\Laranail\AuthKit\Social\Enums\SocialProvider;
use Simtabi\Laranail\Package\Tools\Testing\TestCredentials;

/**
 * Asserts what Socialite actually hands back for each shipped provider.
 *
 * Every other social test fakes the driver, which replaces it entirely: the fake honours whatever
 * raw payload the test supplies, so a test asserting "X links when confirmed_email is present"
 * passes against a driver that never sends one. Two providers shipped unable to authenticate behind
 * a green suite for exactly that reason -- `twitter` resolved to an OAuth 1.0a driver that throws
 * before redirecting, and `linkedin` to a legacy driver whose payload has no email_verified.
 *
 * No network: resolving the driver is enough to catch the class of bug that hid here.
 */
beforeEach(function (): void {
    foreach (SocialProvider::cases() as $provider) {
        config()->set("services.{$provider->driver()}", [
            'client_id'     => 'test-id',
            'client_secret' => TestCredentials::secret('client'),
            'redirect'      => 'https://example.test/callback',
        ]);
    }
});

it('resolves every shipped provider to an OAuth 2 driver', function (SocialProvider $provider): void {
    // OAuth 1.0a providers extend a different base entirely and cannot carry the claims this
    // package reads. Landing on one is the failure mode that shipped.
    expect(Socialite::driver($provider->driver()))
        ->toBeInstanceOf(AbstractProvider::class);
})->with(SocialProvider::cases());

it('resolves X to the OAuth 2 driver, not the legacy OAuth 1.0a one', function (): void {
    // The legacy `twitter` key builds a League TwitterServer that wants identifier/secret and
    // throws on `client_id` -- a 500 on the first click. `x` goes straight to XProvider.
    expect(Socialite::driver(SocialProvider::X->driver()))
        ->toBeInstanceOf(XProvider::class);
});

it('resolves LinkedIn to the OpenID driver, which is the one that returns email_verified', function (): void {
    expect(Socialite::driver(SocialProvider::LINKEDIN->driver()))
        ->toBeInstanceOf(LinkedInOpenIdProvider::class);
});

it('publishes each provider’s credentials under the key its driver reads', function (): void {
    // The slug is stored data; the driver key is Socialite's. Publishing to the slug would leave
    // linkedin-openid with no credentials while services.linkedin held them.
    foreach (SocialProvider::cases() as $provider) {
        expect(config("services.{$provider->driver()}.client_id"))->not->toBeNull();
    }
});
