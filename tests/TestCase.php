<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Social\Tests;

use Workbench\App\Models\User;
use Laravel\Fortify\FortifyServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;
use Simtabi\Laranail\AuthKit\Support\AuthKit;
use Laravel\Socialite\SocialiteServiceProvider;
use SocialiteProviders\Manager\ServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Simtabi\Laranail\AuthKit\Providers\AuthKitServiceProvider;
use Simtabi\Laranail\AuthKit\Social\Providers\SocialServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            // Socialite is this package's dependency now, not the core's.
            SocialiteServiceProvider::class,
            // Apple and PayPal are contributed through SocialiteWasCalled, which only fires when
            // this manager is in place. Without it they resolve as "Driver not supported" in tests
            // while working perfectly in a real application -- a gap that hides driver bugs.
            ServiceProvider::class,
            FortifyServiceProvider::class,
            SanctumServiceProvider::class,
            AuthKitServiceProvider::class,
            SocialServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Social sign-in over the API is off by default, and the routes file reads that flag when
        // it loads -- before any test body runs. Enabled here so the endpoints exist to be tested;
        // the default itself is asserted against the shipped config.
        $app['config']->set('laranail.authkit-social-login.api.enabled', true);

        // Social login's web surface is now a declared AuthKit feature rather than a web.enabled
        // flag of the social package's own, so an application lists it the way it lists every
        // other feature. Read at boot, when the routes load, so it has to be set here.
        $app['config']->set('laranail.authkit.fortify.features', [
            ...(array) $app['config']->get('laranail.authkit.fortify.features', []),
            AuthKit::SOCIAL_LOGIN,
        ]);

        // Generated per run rather than a literal: a scanner cannot tell a placeholder key from a
        // real one, and this line blocks any commit that touches this file.
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $driver = getenv('DB_CONNECTION') ?: 'sqlite';
        $connection = $app['config']->get("database.connections.{$driver}", []);
        $connection['driver'] = $driver;

        if ($driver === 'sqlite') {
            $connection['database'] = getenv('DB_DATABASE') ?: ':memory:';
            $connection['prefix'] = '';
            $connection['foreign_key_constraints'] = true;
        }

        $app['config']->set('database.connections.testing', $connection);

        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        \Orchestra\Testbench\load_migration_paths($this->app, [
            dirname(__DIR__) . '/vendor/orchestra/testbench-core/laravel/migrations',
            dirname(__DIR__) . '/vendor/laravel/fortify/database/migrations',
            dirname(__DIR__) . '/vendor/laravel/sanctum/database/migrations',
            dirname(__DIR__) . '/database/migrations/social',
        ]);
    }
}
