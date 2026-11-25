<?php

declare(strict_types=1);

use Simtabi\Laranail\AuthKit\Social\Enums\SocialProvider;

/**
 * `docs/social-login.md` states the takeover guard is two things: `assertsEmailVerified()`, whether
 * a provider verifies addresses at all, and `hasVerifiedEmail()`, what its payload claims. Only the
 * second was consulted, so the two exhaustive matches were expected to agree rather than made to.
 *
 * They did agree — Facebook is the one provider asserting nothing, and its arm was hardcoded false
 * — so nothing was exploitable. This pins the invariant so a later case cannot quietly break it by
 * reading a claim while asserting nothing, which would link on an unverified address.
 */
it('never reports a verified email for a provider that asserts no verification', function (): void {
    // Payloads that would satisfy every claim-reading arm in hasVerifiedEmail().
    $claims = [
        ['email_verified' => true],
        ['email_verified' => 'true'],
        ['email_verified' => 1],
        ['confirmed_email' => 'ada@example.com'],
    ];

    $checked = 0;

    foreach (SocialProvider::cases() as $provider) {
        if ($provider->assertsEmailVerified()) {
            continue;
        }

        foreach ($claims as $payload) {
            expect($provider->hasVerifiedEmail($payload))->toBeFalse(
                "{$provider->value} asserts no verification yet reported a verified email",
            );
            $checked++;
        }
    }

    // Non-vacuity: if every provider ever asserts verification this test would pass by checking
    // nothing, and would stop being a guard without anyone noticing.
    expect($checked)->toBeGreaterThan(0);
});

it('reads the payload claim for every provider that does assert verification', function (): void {
    $asserting = array_filter(SocialProvider::cases(), fn (SocialProvider $p): bool => $p->assertsEmailVerified());

    expect($asserting)->not->toBeEmpty();

    foreach ($asserting as $provider) {
        // An absent claim is never treated as verified...
        expect($provider->hasVerifiedEmail([]))->toBeFalse("{$provider->value} treated a missing claim as verified");

        // ...and the short-circuit must not have swallowed the real reads.
        $payload = $provider === SocialProvider::X
            ? ['confirmed_email' => 'ada@example.com']
            : ['email_verified' => true];

        expect($provider->hasVerifiedEmail($payload))->toBeTrue("{$provider->value} ignored a genuine verification claim");
    }
});
