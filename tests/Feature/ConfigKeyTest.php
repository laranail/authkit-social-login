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

/*
 * Source scan, because a bare read is not an error: config() returns the inline default and the
 * package silently runs on it. package-tools' assertReadsConfigAtRegisteredKey() only matches the
 * positional form `config('key…')`, and this package writes `config(key: 'key…')` throughout, so it
 * would inspect nothing here and pass. This scan accepts both forms and refuses to pass vacuously.
 */
it('reads no configuration at the bare key outside SocialConfig', function (): void {
    $root = dirname(__DIR__, 2);
    $pattern = '/(?:config\(|->get\(|->has\()\s*(?:key:\s*)?[\'"]authkit-social-login[\'".{]/';

    $files = 0;
    $readSites = 0;
    $offenders = [];

    foreach (['src', 'routes'] as $dir) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$dir}")) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $files++;

            foreach (file($file->getPathname()) ?: [] as $number => $line) {
                $readSites += substr_count($line, 'SocialConfig::get(');

                if (preg_match($pattern, $line) === 1) {
                    $offenders[] = substr($file->getPathname(), strlen($root) + 1) . ':' . ($number + 1) . ' — ' . trim($line);
                }
            }
        }
    }

    // Floors measured 2026-10-04: 33 PHP files under src/ and routes/, 25 SocialConfig::get() calls.
    expect($files)->toBeGreaterThanOrEqual(30)
        ->and($readSites)->toBeGreaterThanOrEqual(20)
        ->and($offenders)->toBe([]);
});

it('matches both call forms in the bare-read pattern', function (): void {
    $pattern = '/(?:config\(|->get\(|->has\()\s*(?:key:\s*)?[\'"]authkit-social-login[\'".{]/';

    expect(preg_match($pattern, "config('authkit-social-login.enabled')"))->toBe(1)
        ->and(preg_match($pattern, "config(key: 'authkit-social-login.web.prefix', default: 'auth')"))->toBe(1)
        ->and(preg_match($pattern, 'config(key: "authkit-social-login.{$slug}.client_id")'))->toBe(1)
        ->and(preg_match($pattern, "config(key: 'authkit-social-login', default: [])"))->toBe(1)
        ->and(preg_match($pattern, "config(key: 'laranail.authkit-social-login.enabled')"))->toBe(0);
});
