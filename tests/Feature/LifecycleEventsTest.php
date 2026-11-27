<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Workbench\App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Facades\Socialite;
use Simtabi\Laranail\AuthKit\Social\Events;
use Laravel\Socialite\Two\User as SocialiteUser;
use Simtabi\Laranail\AuthKit\Social\Enums\SocialProvider;
use Simtabi\Laranail\Package\Tools\Testing\TestCredentials;
use Simtabi\Laranail\AuthKit\Social\Actions\SocialCallbackAction;
use Simtabi\Laranail\AuthKit\Social\Services\SocialAccountService;

function auditSocialProfile(bool $verified): SocialiteUser
{
    $profile = (new SocialiteUser)->setRaw(['email_verified' => $verified])->map([
        'id' => 'audited-provider', 'email' => 'social-audit@example.test', 'name' => 'Audit user',
    ]);
    $profile->token = TestCredentials::secret('provider');

    return $profile;
}

function auditSocialCallbackRequest(): Request
{
    $request = Request::create('/auth/social/google/callback');
    $route = new Route('GET', 'auth/social/{provider}/callback', []);
    $route->bind($request)->setParameter('provider', 'google');
    $request->setRouteResolver(fn () => $route);

    return $request;
}

it('reports accepted social authentication and one persisted link on web and API', function (string $surface): void {
    Event::fake([Events\SocialAuthenticationSucceeded::class, Events\SocialAuthenticationFailed::class, Events\IdentityLinked::class]);
    Socialite::fake('google', auditSocialProfile(true));
    if ($surface === 'web') {
        $result = app(SocialCallbackAction::class)->execute(auditSocialCallbackRequest(), 'web');
        expect($result->isPassed())->toBeTrue();
    } else {
        $this->postJson('/api/auth/social/google/callback', ['code' => TestCredentials::secret('code')])->assertOk();
    }
    $user = User::query()->where('email', 'social-audit@example.test')->sole();
    Event::assertDispatchedTimes(Events\SocialAuthenticationSucceeded::class, 1);
    Event::assertDispatched(Events\SocialAuthenticationSucceeded::class, fn ($event) => $event->userId === (string) $user->getKey()
        && $event->provider === 'google' && $event->guard === 'web' && $event->surface === $surface);
    Event::assertDispatchedTimes(Events\IdentityLinked::class, 1);
    Event::assertDispatched(Events\IdentityLinked::class, fn ($event) => $event->userId === (string) $user->getKey()
        && ! str_contains(json_encode($event), TestCredentials::secret('provider')));
    Event::assertNotDispatched(Events\SocialAuthenticationFailed::class);

    // An existing identity may sign in again but is not linked a second time.
    if ($surface === 'web') {
        app(SocialCallbackAction::class)->execute(auditSocialCallbackRequest(), 'web');
    } else {
        $this->postJson('/api/auth/social/google/callback', ['code' => TestCredentials::secret('code')])->assertOk();
    }
    Event::assertDispatchedTimes(Events\IdentityLinked::class, 1);
    Event::assertDispatchedTimes(Events\SocialAuthenticationSucceeded::class, 2);
})->with(['web', 'api']);

it('reports rejected identities without success or link events', function (string $surface): void {
    Event::fake([Events\SocialAuthenticationSucceeded::class, Events\SocialAuthenticationFailed::class, Events\IdentityLinked::class]);
    Socialite::fake('google', auditSocialProfile(false));
    if ($surface === 'web') {
        expect(app(SocialCallbackAction::class)->execute(auditSocialCallbackRequest(), 'web')->isPassed())->toBeFalse();
    } else {
        $this->postJson('/api/auth/social/google/callback', ['code' => TestCredentials::secret('code')])->assertUnprocessable();
    }
    Event::assertDispatchedTimes(Events\SocialAuthenticationFailed::class, 1);
    Event::assertDispatched(Events\SocialAuthenticationFailed::class, fn ($event) => $event->provider === 'google' && $event->surface === $surface);
    Event::assertNotDispatched(Events\SocialAuthenticationSucceeded::class);
    Event::assertNotDispatched(Events\IdentityLinked::class);
})->with(['web', 'api']);

it('emits unlink only after removing a real identity and never on a refused or repeated removal', function (): void {
    Socialite::fake('google', auditSocialProfile(true));
    $user = app(SocialCallbackAction::class)->execute(auditSocialCallbackRequest(), 'web')->user;
    Event::fake([Events\IdentityUnlinked::class]);
    $accounts = app(SocialAccountService::class);
    expect($accounts->unlink($user, SocialProvider::GOOGLE))->toBeFalse();
    Event::assertNotDispatched(Events\IdentityUnlinked::class);
    config()->set('laranail.authkit-social-login.unlink.trust_password_column', true);
    expect($accounts->unlink($user, SocialProvider::GOOGLE))->toBeTrue();
    expect($accounts->unlink($user, SocialProvider::GOOGLE))->toBeTrue();
    Event::assertDispatchedTimes(Events\IdentityUnlinked::class, 1);
    Event::assertDispatched(Events\IdentityUnlinked::class, fn ($event) => $event->userId === (string) $user->getKey() && $event->provider === 'google');
});
