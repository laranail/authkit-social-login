<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\AuthKit\Social\Providers\SocialServiceProvider;

it('loads the packaged English messages and falls back from an untranslated locale', function (): void {
    expect(__('laranail/authkit-social-login::messages.authentication_failed'))->toBe('Social authentication failed.');
    app()->setLocale('sw');
    expect(__('laranail/authkit-social-login::messages.authentication_failed'))->toBe('Social authentication failed.');
});

it('publishes translations to the namespaced Laravel override directory', function (): void {
    $paths = ServiceProvider::pathsToPublish(SocialServiceProvider::class, 'laranail::authkit-social-login-translations');
    expect($paths)->toBe([dirname(__DIR__, 2) . '/lang' => lang_path('vendor/laranail/authkit-social-login')]);
});

it('honors application language-file overrides and retains other package defaults', function (): void {
    $directory = sys_get_temp_dir() . '/authkit-social-login-translations-' . bin2hex(random_bytes(8));
    $override = $directory . '/vendor/laranail/authkit-social-login/en';
    File::ensureDirectoryExists($override);
    File::put($override . '/messages.php', "<?php return ['authentication_failed' => 'Application override'];");
    app('translation.loader')->addPath($directory);

    try {
        expect(__('laranail/authkit-social-login::messages.authentication_failed'))->toBe('Application override');
        expect(__('laranail/authkit-social-login::messages.unknown_provider'))->toBe('Unknown social provider.');
    } finally {
        File::deleteDirectory($directory);
    }
});

it('translates social buttons with the provider label', function (): void {
    config()->set('laranail.authkit-social-login.providers', ['google']);
    config()->set('laranail.authkit-social-login.google.client_id', 'google-client');
    app('translator')->addLines(['messages.continue_with_provider' => 'Localized :provider'], 'sw', 'laranail/authkit-social-login');
    app()->setLocale('sw');
    $this->blade('<x-laranail-authkit-social-login::social-buttons />')->assertSee('Localized Google');
});

it('translates the account screen and provider counts', function (): void {
    app('translator')->addLines(['messages.social_accounts' => 'Localized accounts'], 'sw', 'laranail/authkit-social-login');
    app()->setLocale('sw');
    $this->actingAs(Workbench\App\Models\User::factory()->create())->get(route('laranail-social.user-social-accounts.index'))->assertOk()->assertSee('Localized accounts');
    expect(trans_choice('laranail/authkit-social-login::messages.provider_count', 1))->toBe('1 provider')
        ->and(trans_choice('laranail/authkit-social-login::messages.provider_count', 2))->toBe('2 providers');
});

it('uses translated social API failure messages', function (): void {
    app('translator')->addLines(['messages.sign_in_failed' => 'Localized social failure'], 'sw', 'laranail/authkit-social-login');
    app()->setLocale('sw');
    $this->mock(Simtabi\Laranail\AuthKit\Social\Contracts\StatelessSocialCallbackInterface::class)->shouldReceive('execute')->once()->andReturnNull();
    $this->postJson('/api/auth/social/google/callback', ['code' => 'provider-code'])->assertUnprocessable()->assertJsonPath('message', 'Localized social failure');
});
