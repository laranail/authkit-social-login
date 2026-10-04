<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Social\Support;

/**
 * One place that knows where this package's configuration lives.
 *
 * The provider registers the defaults at `laranail.authkit-social-login`. Until 2026-10 they were
 * registered and read at the bare `authkit-social-login` key, which sits in Laravel's flat config map
 * beside every other package's. A config an application published before then landed at the bare
 * `config/authkit-social-login.php` and is still honoured, deprecated: a key it sets wins over the
 * scoped default, so an application that already overrode a value keeps its override. Republish with
 * `--tag=laranail::authkit-social-login-config`, which now writes config/laranail/authkit-social-login.php.
 */
final class SocialConfig
{
    public const string KEY = 'laranail.authkit-social-login';

    /**
     * @deprecated Since 0.1 (2026-10). Read and publish at {@see self::KEY}. Still honoured as a
     *             fallback; the earliest release that could stop honouring it is the next minor after 0.1.
     */
    public const string LEGACY_KEY = 'authkit-social-login';

    /**
     * Read a value, or the whole block when $key is null.
     *
     * The whole block is the scoped block with a legacy one shallow-merged over it -- exactly what
     * mergeConfigFrom() produced when the bare key was the registered one.
     */
    public static function get(?string $key = null, mixed $default = null): mixed
    {
        $config = config();

        if ($key === null) {
            $scoped = $config->get(self::KEY, []);
            $legacy = $config->get(self::LEGACY_KEY);

            $scoped = is_array($scoped) ? $scoped : [];

            return is_array($legacy) ? array_merge($scoped, $legacy) : ($scoped === [] ? $default : $scoped);
        }

        if ($config->has(self::LEGACY_KEY . '.' . $key)) {
            return $config->get(self::LEGACY_KEY . '.' . $key, $default);
        }

        return $config->get(self::KEY . '.' . $key, $default);
    }

    /**
     * Emit a deprecation when an application still configures the package at the bare key.
     *
     * Returns whether one was emitted, so the provider can call it once at boot.
     */
    public static function reportLegacyKey(): bool
    {
        if (! config()->has(self::LEGACY_KEY)) {
            return false;
        }

        trigger_error(
            'laranail/authkit-social-login: configuration under the bare "authkit-social-login" key is deprecated. '
            . 'Move it to "laranail.authkit-social-login" -- republish with '
            . '`php artisan vendor:publish --tag=laranail::authkit-social-login-config`, which writes '
            . 'config/laranail/authkit-social-login.php. The bare key will stop being read in the next minor after 0.1.',
            E_USER_DEPRECATED,
        );

        return true;
    }
}
