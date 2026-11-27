<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\AuthKit\Social\Support\SocialConfig;
use Simtabi\Laranail\AuthKit\Social\Support\SocialProviders;
use Simtabi\Laranail\AuthKit\Social\Support\SocialWebRoutes;
use Simtabi\Laranail\AuthKit\Social\Providers\SocialServiceProvider;

/*
 * The configuration lives at `laranail.authkit-social-login`. Until 2026-10 it was registered and read
 * at the bare `authkit-social-login` key, which sits in the same flat map as every other package's
 * config. A config an application published under the bare key is still honoured, deprecated.
 */

it('registers its defaults at the vendor-scoped key and nothing at the bare one', function (): void {
    expect(config(SocialConfig::KEY))->toBeArray()->toHaveKey('enabled')
        ->and(config(SocialConfig::LEGACY_KEY))->toBeNull();
});

it('publishes the config to the nested path Laravel reads the scoped key back from', function (): void {
    $paths = ServiceProvider::pathsToPublish(
        provider: SocialServiceProvider::class,
        group: 'laranail::authkit-social-login-config',
    );

    expect(array_values($paths))->toBe([config_path('laranail/authkit-social-login.php')]);
});

it('changes behaviour from a value set at the scoped key', function (): void {
    config()->set(SocialConfig::KEY . '.providers', ['google']);
    config()->set(SocialConfig::KEY . '.google.client_id', 'scoped-client');
    config()->set(SocialConfig::KEY . '.web.after_login', '/scoped-home');

    expect(SocialProviders::buttons())->toHaveCount(1)
        ->and(SocialWebRoutes::afterLoginRedirect())->toBe('/scoped-home');

    config()->set(SocialConfig::KEY . '.enabled', false);

    expect(SocialProviders::buttons())->toBe([]);
});

it('still honours a value an application set at the deprecated bare key', function (): void {
    config()->set(SocialConfig::KEY . '.providers', ['google']);
    config()->set(SocialConfig::KEY . '.google.client_id', 'scoped-client');
    config()->set(SocialConfig::LEGACY_KEY, ['web' => ['after_login' => '/legacy-home']]);

    // A partial legacy file overrides only what it names; the rest still comes from the scoped key.
    expect(SocialWebRoutes::afterLoginRedirect())->toBe('/legacy-home')
        ->and(SocialProviders::buttons())->toHaveCount(1);

    config()->set(SocialConfig::LEGACY_KEY . '.enabled', false);

    expect(SocialProviders::buttons())->toBe([]);
});

it('merges a legacy whole block over the scoped one, the way mergeConfigFrom used to', function (): void {
    config()->set(SocialConfig::LEGACY_KEY, ['google' => ['client_id' => 'legacy-client']]);

    $all = SocialConfig::get();

    expect($all['google'])->toBe(['client_id' => 'legacy-client'])
        ->and($all)->toHaveKey('enabled');
});

it('emits a deprecation naming the replacement when the bare key is in use', function (): void {
    $captured = [];
    set_error_handler(function (int $level, string $message) use (&$captured): bool {
        $captured[] = [$level, $message];

        return true;
    }, E_USER_DEPRECATED);

    try {
        expect(SocialConfig::reportLegacyKey())->toBeFalse();

        config()->set(SocialConfig::LEGACY_KEY, ['enabled' => true]);

        expect(SocialConfig::reportLegacyKey())->toBeTrue();
    } finally {
        restore_error_handler();
    }

    expect($captured)->toHaveCount(1)
        ->and($captured[0][0])->toBe(E_USER_DEPRECATED)
        ->and($captured[0][1])->toContain('laranail.authkit-social-login')
        ->and($captured[0][1])->toContain('config/laranail/authkit-social-login.php');
});
