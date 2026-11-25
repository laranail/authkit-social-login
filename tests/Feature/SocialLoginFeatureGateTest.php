<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Simtabi\Laranail\AuthKit\Support\AuthKit;
use Simtabi\Laranail\AuthKit\Social\Support\SocialProviders;

/**
 * Social login's user-facing surface is one switch: the `social-login` feature on AuthKit's own
 * list. It replaced `laranail.authkit-social-login.web.enabled`, which was a second place that
 * could disagree with the first — an application could have providers configured, the package
 * enabled, and still see nothing, with three settings saying yes and one saying no.
 *
 * The package master switch `enabled` is unchanged and is a different scope: it turns the whole
 * package off, API included. This gate is the web surface only.
 */
trait SocialLoginFeatureOff
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laranail.authkit.fortify.features', array_values(array_filter(
            (array) $app['config']->get('laranail.authkit.fortify.features', []),
            static fn (string $feature): bool => $feature !== AuthKit::SOCIAL_LOGIN,
        )));
    }
}

uses(SocialLoginFeatureOff::class);

it('reports the feature as off once it is not declared', function (): void {
    expect(AuthKit::socialLoginEnabled())->toBeFalse();
});

it('renders no provider buttons while the feature is undeclared', function (): void {
    // Providers are configured and the package is enabled: the feature is the only thing missing.
    config()->set('laranail.authkit-social-login.providers', ['google']);
    config()->set('laranail.authkit-social-login.enabled', true);

    expect(SocialProviders::buttons())->toBe([]);
});

it('mounts no browser social routes while the feature is undeclared', function (): void {
    $names = array_values(array_filter(
        array_keys(Route::getRoutes()->getRoutesByName()),
        static fn (string $name): bool => str_contains($name, 'social'),
    ));

    // The API pair survives on purpose: it has its own switch, `api.enabled`. This feature gates
    // the browser surface, so what must be gone is everything that is not those two.
    expect($names)->toBe(['laranail-auth-api.social.redirect', 'laranail-auth-api.social.callback']);
});

// The removed key must not quietly come back as a second switch.
it('no longer ships a web.enabled key', function (): void {
    $shipped = require dirname(__DIR__, 2) . '/config/authkit-social-login.php';

    expect($shipped['web'])->toBeArray()
        ->and($shipped['web'])->not->toHaveKey('enabled');
});
