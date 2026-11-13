<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Session\Store;
use Workbench\App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Session\ArraySessionHandler;
use Laravel\Socialite\Two\User as SocialiteUser;
use Simtabi\Laranail\AuthKit\Social\Models\Social;
use Simtabi\Laranail\AuthKit\Enums\IdentityLinkFailure;
use Simtabi\Laranail\AuthKit\Social\Enums\SocialProvider;
use Simtabi\Laranail\AuthKit\Exceptions\IdentityLinkException;
use Simtabi\Laranail\AuthKit\Social\Actions\ConnectSocialAccount;

function connectIdentity(string $id = '123456789'): SocialiteUser
{
    $raw = [
        'id'             => $id,
        'name'           => 'John Doe',
        'nickname'       => 'johndoe',
        'email'          => 'john@example.com',
        'avatar'         => 'https://example.com/avatar.jpg',
        'email_verified' => true,
    ];

    $user = new SocialiteUser;
    $user->setRaw($raw);
    $user->map($raw);
    $user->token = 'mock-token';
    $user->refreshToken = 'mock-refresh-token';
    $user->expiresIn = 3600;

    return $user;
}

function connectSession(bool $confirmed = true, ?string $id = null): Store
{
    $store = new Store('authkit_connect_test', new ArraySessionHandler(120), $id ?? Str::random(40));
    $store->start();

    if ($confirmed) {
        $store->put('auth.password_confirmed_at', time());
    }

    return $store;
}

function connectRequest(Store $session, string $provider = 'google', string $leg = 'connect'): Request
{
    $uri = "/auth/social/{$provider}/{$leg}";
    $request = Request::create(uri: $uri, method: 'GET');
    $request->setRouteResolver(fn () => (new Route('GET', "/auth/social/{provider}/{$leg}", []))->bind($request));
    $request->route()->setParameter('provider', $provider);
    $request->setLaravelSession($session);

    return $request;
}

function connector(): ConnectSocialAccount
{
    return app(ConnectSocialAccount::class);
}

function ownedIdentity(User $owner, string $id = '123456789'): Social
{
    return Social::query()->create([
        'socialable_type' => $owner::class,
        'socialable_id'   => $owner->getAuthIdentifier(),
        'provider'        => SocialProvider::GOOGLE,
        'provider_id'     => $id,
        'name'            => 'John Doe',
        'email'           => 'john@example.com',
    ]);
}

function refusal(callable $callback): IdentityLinkFailure
{
    try {
        $callback();
    } catch (IdentityLinkException $exception) {
        return $exception->failure;
    }

    throw new RuntimeException('Expected the connect attempt to be refused, and it was not.');
}

const CB = 'https://app.test/auth/social/google/connect/callback';

/* ------------------------------------------------------------------ starting */

it('refuses to start without a recently confirmed credential', function (): void {
    Socialite::fake('google', connectIdentity());
    $user = User::factory()->create();

    $reason = refusal(fn () => connector()->start(
        request: connectRequest(connectSession(confirmed: false)),
        user: $user,
        guard: 'web',
        callbackUrl: CB,
    ));

    expect($reason)->toBe(IdentityLinkFailure::RecentAuthenticationRequired);
});

it('starts an attempt and parks the token for the trip to the provider', function (): void {
    Socialite::fake('google', connectIdentity());
    $user = User::factory()->create();
    $session = connectSession();

    $result = connector()->start(connectRequest($session), $user, 'web', CB);

    expect($result->url)->toBeString()->not->toBe('')
        ->and(strlen($result->token))->toBe(64)
        ->and($session->get(ConnectSocialAccount::SESSION_KEY))->toBe($result->token);
});

/* ---------------------------------------------------------------- the four #12 names */

it('links the identity to the user who started the attempt', function (): void {
    Socialite::fake('google', connectIdentity());
    $user = User::factory()->create();
    $session = connectSession();

    connector()->start(connectRequest($session), $user, 'web', CB);
    $social = connector()->complete(connectRequest($session, leg: 'connect/callback'), $user, 'web', CB);

    expect(Social::query()->count())->toBe(1)
        ->and($social->socialable_id)->toBe($user->getAuthIdentifier())
        ->and($social->provider_id)->toBe('123456789');
});

it('refuses a replayed callback', function (): void {
    Socialite::fake('google', connectIdentity());
    $user = User::factory()->create();
    $session = connectSession();

    connector()->start(connectRequest($session), $user, 'web', CB);
    connector()->complete(connectRequest($session, leg: 'connect/callback'), $user, 'web', CB);

    $reason = refusal(fn () => connector()->complete(
        connectRequest($session, leg: 'connect/callback'),
        $user,
        'web',
        CB,
    ));

    expect($reason)->toBe(IdentityLinkFailure::IntentInvalid)
        ->and(Social::query()->count())->toBe(1);
});

it('refuses an attempt finished by a different account', function (): void {
    Socialite::fake('google', connectIdentity());
    $initiator = User::factory()->create();
    $other = User::factory()->create();
    $session = connectSession();

    connector()->start(connectRequest($session), $initiator, 'web', CB);

    $reason = refusal(fn () => connector()->complete(
        connectRequest($session, leg: 'connect/callback'),
        $other,
        'web',
        CB,
    ));

    expect($reason)->toBe(IdentityLinkFailure::IntentMismatch)
        ->and(Social::query()->count())->toBe(0);
});

it('refuses an identity already linked elsewhere, and leaves it with its owner', function (): void {
    Socialite::fake('google', connectIdentity());
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $user = User::factory()->create(['email' => 'connector@example.com']);
    $link = ownedIdentity($owner);
    $session = connectSession();

    connector()->start(connectRequest($session), $user, 'web', CB);

    $reason = refusal(fn () => connector()->complete(
        connectRequest($session, leg: 'connect/callback'),
        $user,
        'web',
        CB,
    ));

    expect($reason)->toBe(IdentityLinkFailure::IdentityOwnedByAnotherUser)
        ->and(Social::query()->count())->toBe(1)
        ->and($link->fresh()->socialable_id)->toBe($owner->getAuthIdentifier());
});

/* ------------------------------------------------------------------ properties */

it('is idempotent for an identity this user already owns', function (): void {
    Socialite::fake('google', connectIdentity());
    $user = User::factory()->create();
    $existing = ownedIdentity($user);
    $session = connectSession();

    connector()->start(connectRequest($session), $user, 'web', CB);
    $social = connector()->complete(connectRequest($session, leg: 'connect/callback'), $user, 'web', CB);

    expect(Social::query()->count())->toBe(1)
        ->and($social->getKey())->toBe($existing->getKey());
});

it('signs nobody in while connecting', function (): void {
    Socialite::fake('google', connectIdentity());
    $user = User::factory()->create();
    $session = connectSession();

    connector()->start(connectRequest($session), $user, 'web', CB);
    connector()->complete(connectRequest($session, leg: 'connect/callback'), $user, 'web', CB);

    expect(auth()->guard('web')->check())->toBeFalse();
});

/*
 * The distinction the issue asks for is which endpoint you reach, not what the resolver infers.
 * Sign-in stays behind `guest`; connecting is behind `auth`.
 */
it('mounts connecting behind auth and signing in behind guest', function (): void {
    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->mapWithKeys(fn ($route): array => [$route->getName() => $route->gatherMiddleware()])
        ->filter(fn ($m, $name): bool => is_string($name) && str_contains($name, 'social.'));

    $connect = $routes->first(fn ($m, $name): bool => str_ends_with((string) $name, 'social.connect'));
    $callback = $routes->first(fn ($m, $name): bool => str_ends_with((string) $name, 'social.connect.callback'));
    $signIn = $routes->first(fn ($m, $name): bool => str_ends_with((string) $name, 'social.redirect'));

    expect($connect)->not->toBeNull()
        ->and($callback)->not->toBeNull()
        ->and($signIn)->not->toBeNull()
        ->and(collect($connect)->contains(fn ($m): bool => str_starts_with((string) $m, 'auth:')))->toBeTrue()
        ->and(collect($callback)->contains(fn ($m): bool => str_starts_with((string) $m, 'auth:')))->toBeTrue()
        ->and(collect($signIn)->contains(fn ($m): bool => str_starts_with((string) $m, 'guest:')))->toBeTrue();
});
