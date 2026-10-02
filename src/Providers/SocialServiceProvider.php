<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Social\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\AuthKit\Social\Actions;
use Simtabi\Laranail\AuthKit\Social\Services;
use Simtabi\Laranail\AuthKit\Social\Contracts;
use SocialiteProviders\Apple\AppleExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use Simtabi\Laranail\AuthKit\Social\Enums\SocialProvider;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\AuthKit\Contracts\IdentityProviderRegistryInterface;

/**
 * Social login for laranail/authkit.
 *
 * Everything here moved out of AuthKitServiceProvider when social login left the core. Extends the
 * core through its published seams and never edits it. Every public name is vendor-scoped: the
 * config key is laranail.authkit-social-login and publish tags are laranail::authkit-social-login-*, because
 * Laravel keeps these in flat global maps where a second package claiming the same key silently
 * replaces the first.
 *
 * THE ENABLED GATE DEFAULTS TRUE HERE, unlike its sibling packages. authkit-sso, -ldap, -oauth and
 * -tenancy default false because they are inert placeholders. This package took over behaviour the
 * core used to provide, so an application upgrading across the extraction already has social login
 * configured and in use -- defaulting false would switch it off during a routine `composer update`
 * with no error anywhere.
 */
class SocialServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laranail/authkit-social-login')
            ->publish(
                paths: ['config/laranail/authkit-social-login.php' => config_path(path: 'laranail/authkit-social-login.php')],
                tag: 'laranail::authkit-social-login-config',
            )
            /*
             * The core published this same tag until the extraction. It drops it in the same change,
             * so exactly one package owns it -- NamingConventionTest cannot catch a duplicate,
             * because it only rejects tags missing the laranail:: prefix.
             */
            ->publish(
                paths: ['database/migrations/social' => database_path(path: 'migrations')],
                tag: 'laranail::authkit-social-login-migrations',
            )
            ->publish(
                paths: ['routes/web.php' => base_path(path: 'routes/laranail-authkit-social-login-web.php')],
                tag: 'laranail::authkit-social-login-routes',
            );
    }

    public function packageRegistered(): void
    {
        $this->mergeConfigFrom(path: $this->packagePath('config/laranail/authkit-social-login.php'), key: 'laranail.authkit-social-login');

        $this->app->bind(abstract: Contracts\SocialRedirectActionInterface::class, concrete: Actions\SocialRedirectAction::class);
        $this->app->bind(abstract: Contracts\SocialCallbackActionInterface::class, concrete: Actions\SocialCallbackAction::class);
        $this->app->bind(abstract: Contracts\CreateSocialAccountActionInterface::class, concrete: Actions\CreateSocialAccountAction::class);
        $this->app->bind(abstract: Contracts\ResolveSocialIdentityInterface::class, concrete: Actions\ResolveSocialIdentity::class);
    }

    public function packageBooted(): void
    {
        $this->loadViewsFrom($this->packagePath('resources/views'), 'laranail/authkit-social-login');
        Blade::anonymousComponentPath($this->packagePath('resources/views/components'), 'laranail-authkit-social-login');

        // `default: true` matches the shipped config. Reading false when the key is absent would
        // disable social login for anyone who has not published the config file.
        if (! config(key: 'laranail.authkit-social-login.enabled', default: true)) {
            return;
        }

        $this->app->bind(
            abstract: Contracts\StatelessSocialCallbackInterface::class,
            concrete: Actions\StatelessSocialCallback::class,
        );

        $this->app->bind(
            abstract: Contracts\UnlinkSocialAccountInterface::class,
            concrete: Actions\UnlinkSocialAccount::class,
        );

        if (config(key: 'laranail.authkit-social-login.web.enabled', default: true)
            && config(key: 'laranail.authkit-social-login.web.routes_mode', default: 'package') === 'package') {
            $this->loadRoutesFrom($this->packagePath('routes/web.php'));
        }

        $this->loadRoutesFrom($this->packagePath('routes/api.php'));

        $this->publishProviderCredentials();
        $this->registerPayPalProvider();
        $this->registerAppleProvider();
        $this->registerContributedProviders();
    }

    /**
     * Socialite reads credentials from Laravel's own `services.*`, not from this package's config,
     * so each provider block is copied across.
     *
     * This was called registerConfig() in the core -- a generic name for a social-only method, which
     * is exactly why it was easy to leave behind during the extraction.
     */
    private function publishProviderCredentials(): void
    {
        foreach (config(key: 'laranail.authkit-social-login', default: []) as $slug => $providerConfig) {
            if ($slug === 'enabled' || ! is_array(value: $providerConfig)) {
                continue;
            }

            // Published under the *driver* key, which is not always the slug. Socialite reads
            // services.linkedin-openid for the OpenID LinkedIn driver, so publishing our
            // `linkedin` block to services.linkedin would leave the driver we actually use with
            // no credentials at all.
            $driver = SocialProvider::tryFrom($slug)?->driver() ?? $slug;

            config()->set(key: "services.{$driver}", value: $providerConfig);
        }
    }

    private function registerPayPalProvider(): void
    {
        Event::listen(
            events: SocialiteWasCalled::class,
            listener: function (SocialiteWasCalled $event): void {
                $event->extendSocialite(
                    providerName: 'paypal',
                    providerClass: Services\PayPalSocialProvider::class,
                );
            },
        );
    }

    /**
     * Bind the Socialite driver of every provider contributed through the registry.
     *
     * Registering a provider with the registry and binding its Socialite driver were separate
     * steps, and nothing warned when the second was forgotten -- the slug resolved and then
     * Socialite threw at the callback. A provider that supplies a driverClass now needs one
     * registration.
     *
     * The listener reads the registry when the event fires rather than closing over it, so the
     * order in which sub-packages boot does not matter.
     *
     * SocialiteProviders dispatches SocialiteWasCalled exactly once, from its own app->booted()
     * callback -- not per driver resolution. A provider registered during any package's boot() is
     * therefore seen, and one registered after the application has finished booting is not. That is
     * the same constraint every SocialiteProviders package works under; register in boot().
     */
    private function registerContributedProviders(): void
    {
        Event::listen(
            events: SocialiteWasCalled::class,
            listener: function (SocialiteWasCalled $event): void {
                foreach (app(abstract: IdentityProviderRegistryInterface::class)->all() as $provider) {
                    if ($provider->driverClass !== null) {
                        $event->extendSocialite(
                            providerName: $provider->driver(),
                            providerClass: $provider->driverClass,
                        );
                    }
                }
            },
        );
    }

    /**
     * Apple ships its own Socialite extension, so this only wires the listener up.
     *
     * Registering it here rather than relying on the package's auto-discovery keeps every
     * driver this package owns visible in one place, and keeps the listener bound even when
     * the consuming application has disabled package discovery.
     */
    private function registerAppleProvider(): void
    {
        Event::listen(
            events: SocialiteWasCalled::class,
            listener: [AppleExtendSocialite::class, 'handle'],
        );
    }
}
