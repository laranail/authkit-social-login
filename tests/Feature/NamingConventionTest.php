<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\AuthKit\Providers\AuthKitServiceProvider;
use Simtabi\Laranail\AuthKit\Social\Providers\SocialServiceProvider;

/*
 * Every public name a package registers lands in a flat, global registry. A second package claiming
 * the same key does not collide loudly -- it silently replaces the first, and the damage surfaces
 * far away as a missing view or the wrong config. These assertions read the LIVE registries rather
 * than the provider source, so the guard survives a refactor of the registration code.
 */

it('keeps its configuration under the laranail namespace', function (): void {
    expect(config('laranail.authkit-social-login'))->toBeArray()
        ->and(config('authkit-social-login'))->toBeNull();
});

it('never registers a bare publish tag', function (): void {
    $bare = array_filter(
        array_keys(ServiceProvider::publishableGroups()),
        fn (string $tag): bool => str_contains($tag, 'authkit-social-login') && ! str_starts_with($tag, 'laranail::'),
    );

    expect(array_values($bare))->toBe([]);
});

/*
 * The sibling packages assert this defaults to FALSE, because installing an inert placeholder must
 * not change how an application authenticates. This package inverts that on purpose.
 *
 * Social login used to live inside laranail/authkit. An application upgrading across the extraction
 * already has it configured and in use, so defaulting to false would switch it off during a routine
 * `composer update` -- no error, no log line, the buttons just stop rendering. Defaulting to true
 * keeps the upgrade silent in the way that matters: nothing changes.
 */
it('is on by default, because it carries behaviour the core used to provide', function (): void {
    expect(config('laranail.authkit-social-login.enabled'))->toBeTrue();
});

it('can still be switched off deliberately', function (): void {
    config()->set('laranail.authkit-social-login.enabled', false);

    expect(config('laranail.authkit-social-login.enabled'))->toBeFalse();
});

/*
 * Moved here from authkit's PasskeyTest, where a social assertion had been sitting inside the
 * passkey suite. It asserts the migrations publish under this package now -- the core published the
 * same tag until the extraction, and exactly one package may own it.
 */
it('publishes the social migrations under this package', function (): void {
    $paths = ServiceProvider::pathsToPublish(
        provider: SocialServiceProvider::class,
        group: 'laranail::authkit-social-login-migrations',
    );

    expect(array_map('realpath', array_keys($paths)))
        ->toContain(realpath(dirname(__DIR__, 2) . '/database/migrations/social'));
});

it('is the only package publishing that tag', function (): void {
    $fromCore = ServiceProvider::pathsToPublish(
        provider: AuthKitServiceProvider::class,
        group: 'laranail::authkit-social-login-migrations',
    );

    expect($fromCore)->toBe([]);
});
