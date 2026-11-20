<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Illuminate\Http\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Session\Store;
use Workbench\App\Models\User;
use GuzzleHttp\Handler\MockHandler;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Session\ArraySessionHandler;
use Laravel\Socialite\Two\FacebookProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Simtabi\Laranail\AuthKit\Social\Models\Social;
use Simtabi\Laranail\AuthKit\Social\Enums\SocialProvider;
use Simtabi\Laranail\Package\Tools\Testing\TestCredentials;
use Simtabi\Laranail\AuthKit\Social\Services\GitHubSocialProvider;
use Simtabi\Laranail\AuthKit\Social\Contracts\CreateSocialAccountActionInterface;

function providerResponse(array $body, int $status = 200): Response
{
    return new Response($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR));
}

function providerProfile(): array
{
    return ['id' => '12345', 'node_id' => 'node', 'login' => 'octocat', 'name' => 'Provider user', 'email' => 'member@example.com', 'avatar_url' => 'https://example.com/avatar.png'];
}

function realProviderDriver(string $provider, array $responses, bool $stateless = true)
{
    config()->set("services.{$provider}", [
        'client_id'     => 'client-id',
        'client_secret' => TestCredentials::secret('client'),
        'redirect'      => "https://app.test/auth/social/{$provider}/callback",
    ]);
    $driver = Socialite::driver($provider);
    $driver->setHttpClient(new Client(['handler' => HandlerStack::create(new MockHandler($responses))]));
    $request = Request::create('/callback', 'GET', ['code' => 'authorization-code', 'state' => 'valid-state']);
    $session = new Store('provider_test', new ArraySessionHandler(120));
    $session->put('state', 'valid-state');
    $request->setLaravelSession($session);
    $driver->setRequest($request);

    if ($stateless) {
        $driver->stateless();
    }

    Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);

    return $driver;
}

function providerIdentity(?string $id = '12345', ?string $email = 'member@example.com'): SocialiteUser
{
    $raw = ['id' => $id, 'email' => $email, 'name' => 'Provider user', 'email_verified' => true, 'verified' => true];
    $user = (new SocialiteUser)->setRaw($raw)->map($raw);
    $user->token = TestCredentials::secret('provider');

    return $user;
}

it('registers credentials, drivers and buttons for both new providers', function (): void {
    $config = require dirname(__DIR__, 2) . '/config/authkit-social-login.php';
    config()->set('laranail.authkit-social-login.providers', ['github', 'facebook']);
    foreach (['github', 'facebook'] as $provider) {
        expect(array_keys($config[$provider]))->toContain('client_id', 'client_secret', 'redirect', 'scopes');
        expect(config("services.{$provider}"))->toBe($config[$provider]);
        config()->set("laranail.authkit-social-login.{$provider}.client_id", 'client-id');
        config()->set("services.{$provider}", ['client_id' => 'client-id', 'client_secret' => TestCredentials::secret('client'), 'redirect' => 'https://app.test/callback']);
    }
    expect(Socialite::driver('github'))->toBeInstanceOf(GitHubSocialProvider::class)
        ->and(Socialite::driver('facebook'))->toBeInstanceOf(FacebookProvider::class);
    $this->blade('<x-laranail-authkit-social-login::social-buttons />')->assertSee('Continue with GitHub')->assertSee('Continue with Facebook');
});

it('redirects both providers with their configured scopes', function (string $provider, string $scope): void {
    config()->set("services.{$provider}", ['client_id' => 'client-id', 'client_secret' => TestCredentials::secret('client'), 'redirect' => 'https://app.test/callback']);
    $response = $this->get("/auth/social/{$provider}")->assertRedirect();
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    expect($query['scope'])->toContain($scope)->and($query['state'])->not->toBeEmpty();
})->with([['github', 'user:email'], ['facebook', 'email']]);

it('uses GitHubs verified primary email rather than the public profile email', function (): void {
    $driver = realProviderDriver('github', [
        providerResponse(providerProfile()),
        providerResponse([
            ['email' => 'member@example.com', 'primary' => false, 'verified' => true],
            ['email' => 'primary@example.com', 'primary' => true, 'verified' => true],
        ]),
    ]);
    $user = $driver->userFromToken(TestCredentials::secret('provider'));
    expect($user->getEmail())->toBe('primary@example.com')
        ->and(SocialProvider::GITHUB->hasVerifiedEmail($user->getRaw()))->toBeTrue();
});

it('does not trust GitHub email without a verified primary address', function (array $emails, int $status): void {
    $driver = realProviderDriver('github', [providerResponse(providerProfile()), providerResponse($emails, $status)]);
    $user = $driver->userFromToken(TestCredentials::secret('provider'));
    expect($user->getEmail())->toBeNull()->and(SocialProvider::GITHUB->hasVerifiedEmail($user->getRaw()))->toBeFalse();
})->with([
    'unverified primary' => [[['email' => 'member@example.com', 'primary' => true, 'verified' => false]], 200],
    'no email'           => [[], 200],
    'permission denied'  => [[], 403],
]);

it('does not mark GitHubs public profile email verified when email scope is removed', function (): void {
    $driver = realProviderDriver('github', [providerResponse(providerProfile())]);
    $user = $driver->setScopes(['read:user'])->userFromToken(TestCredentials::secret('provider'));
    expect(SocialProvider::GITHUB->hasVerifiedEmail($user->getRaw()))->toBeFalse();
});

it('completes GitHub web and API code exchanges using the real driver', function (bool $api, bool $existing): void {
    if ($existing) {
        User::factory()->create(['email' => 'member@example.com']);
    }
    realProviderDriver('github', [
        providerResponse(['access_token' => TestCredentials::secret('provider')]),
        providerResponse(providerProfile()),
        providerResponse([['email' => 'member@example.com', 'primary' => true, 'verified' => true]]),
    ], $api);
    if ($api) {
        $this->postJson('/api/auth/social/github/callback', ['code' => 'authorization-code'])->assertOk()->assertJsonStructure(['data' => ['token']]);
    } else {
        $this->get('/auth/social/github/callback?code=authorization-code&state=valid-state')->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }
    $this->assertDatabaseHas('users', ['email' => 'member@example.com']);
    $this->assertDatabaseHas('socials', ['provider' => 'github', 'provider_id' => '12345']);
    expect(User::query()->count())->toBe(1);
})->with([[false, false], [true, false], [false, true], [true, true]]);

it('never trusts Facebook account verification as email verification', function (): void {
    expect(SocialProvider::FACEBOOK->assertsEmailVerified())->toBeFalse()
        ->and(SocialProvider::FACEBOOK->hasVerifiedEmail(['email_verified' => true, 'verified' => true]))->toBeFalse();
    $existing = User::factory()->create(['email' => 'member@example.com']);
    Socialite::fake('facebook', providerIdentity());
    $this->get('/auth/social/facebook/callback')->assertRedirect('/login')->assertSessionHasErrors('email');
    $this->assertGuest();
    expect(Social::query()->count())->toBe(0)->and(User::query()->count())->toBe(1);
});

it('explicitly connects Facebook then signs in through the linked provider ID without an email', function (): void {
    $user = User::factory()->create();
    Socialite::fake('facebook', providerIdentity(email: null));
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->get('/auth/social/facebook/connect')->assertRedirect();
    $this->withCookie(config('session.cookie'), session()->getId())->get('/auth/social/facebook/connect/callback')->assertRedirect()->assertSessionHas('status', 'social-account-connected');
    $this->assertDatabaseHas('socials', ['provider' => 'facebook', 'provider_id' => '12345', 'socialable_id' => $user->id]);
    auth()->logout();
    $this->get('/auth/social/facebook/callback')->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
});

it('signs a linked Facebook identity into web and API through the real driver', function (bool $api): void {
    $user = User::factory()->create();
    app(CreateSocialAccountActionInterface::class)->execute($user, SocialProvider::FACEBOOK, providerIdentity());
    realProviderDriver('facebook', [providerResponse(['access_token' => TestCredentials::secret('provider')]), providerResponse(providerProfile())], $api);
    if ($api) {
        $this->postJson('/api/auth/social/facebook/callback', ['code' => 'authorization-code'])->assertOk()->assertJsonStructure(['data' => ['token']]);
    } else {
        $this->get('/auth/social/facebook/callback?code=authorization-code&state=valid-state')->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }
})->with([false, true]);

it('rejects cancelled provider exchanges without creating an identity', function (string $provider, bool $api): void {
    realProviderDriver($provider, [providerResponse(['error' => 'access_denied'], 400)], $api);
    if ($api) {
        $this->postJson("/api/auth/social/{$provider}/callback", ['code' => 'rejected-code'])->assertUnprocessable();
    } else {
        $this->get("/auth/social/{$provider}/callback?error=access_denied")->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();
    }
    expect(Social::query()->count())->toBe(0)->and(User::query()->count())->toBe(0);
})->with([['github', false], ['facebook', false], ['github', true], ['facebook', true]]);

it('rejects invalid OAuth state without an identity or session', function (string $provider): void {
    $driver = realProviderDriver($provider, [], false);
    $request = Request::create('/callback?code=authorization-code&state=wrong-state');
    $session = new Store('invalid_state', new ArraySessionHandler(120));
    $session->put('state', 'valid-state');
    $request->setLaravelSession($session);
    $driver->setRequest($request);
    $this->get("/auth/social/{$provider}/callback")->assertRedirect('/login')->assertSessionHasErrors('email');
    $this->assertGuest();
    expect(Social::query()->count())->toBe(0);
})->with(['github', 'facebook']);

it('rejects provider responses without an identity', function (string $provider): void {
    Socialite::fake($provider, providerIdentity(id: null));
    $this->postJson("/api/auth/social/{$provider}/callback", ['code' => 'authorization-code'])->assertUnprocessable();
    expect(Social::query()->count())->toBe(0)->and(User::query()->count())->toBe(0);
})->with(['github', 'facebook']);

it('handles a rejected explicit-link exchange without changing the signed-in account', function (): void {
    $user = User::factory()->create();
    realProviderDriver('facebook', [providerResponse(['error' => 'access_denied'], 400)]);
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->get('/auth/social/facebook/connect')->assertRedirect();
    $this->withCookie(config('session.cookie'), session()->getId())->get('/auth/social/facebook/connect/callback')->assertRedirect()->assertSessionHasErrors('provider');
    $this->assertAuthenticatedAs($user);
    expect(Social::query()->count())->toBe(0);
});
