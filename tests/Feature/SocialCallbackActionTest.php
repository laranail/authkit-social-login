<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Workbench\App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Simtabi\Laranail\AuthKit\Social\Models\Social;
use Simtabi\Laranail\AuthKit\Social\Enums\SocialProvider;
use Simtabi\Laranail\Package\Tools\Testing\TestCredentials;
use Simtabi\Laranail\AuthKit\Social\Actions\SocialCallbackAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function callbackRequest(string $provider): Request
{
    $request = Request::create(uri: "/auth/social/{$provider}/callback", method: 'GET');
    $request->setRouteResolver(fn () => (new Route('GET', '/auth/social/{provider}/callback', []))->bind($request));
    $request->route()->setParameter('provider', $provider);

    return $request;
}

beforeEach(function (): void {
    $rawUser = [
        'id'             => '123456789',
        'name'           => 'John Doe',
        'nickname'       => 'johndoe',
        'email'          => 'john@example.com',
        'avatar'         => 'https://example.com/avatar.jpg',
        'email_verified' => true,
    ];

    $this->socialiteUser = new SocialiteUser;
    $this->socialiteUser->setRaw($rawUser);
    $this->socialiteUser->map($rawUser);
    $this->socialiteUser->token = 'mock-token';
    $this->socialiteUser->refreshToken = 'mock-refresh-token';
    $this->socialiteUser->expiresIn = 3600;
});

it('creates a new user and social account when no match exists', function (): void {
    Socialite::fake(SocialProvider::GOOGLE->value, $this->socialiteUser);

    $result = app(SocialCallbackAction::class)->execute(
        request: callbackRequest('google'),
        guard: 'web',
    );

    expect($result->isPassed())->toBeTrue()
        ->and($result->user->email)->toBe('john@example.com')
        ->and(Social::query()->count())->toBe(1);
});

it('returns existing user when social account already exists', function (): void {
    Socialite::fake(SocialProvider::GOOGLE->value, $this->socialiteUser);

    $existingUser = User::factory()->create(['email' => 'john@example.com']);
    Social::query()->create([
        'socialable_type' => get_class($existingUser),
        'socialable_id'   => $existingUser->getAuthIdentifier(),
        'provider'        => 'google',
        'provider_id'     => '123456789',
        'name'            => 'John Doe',
        'email'           => 'john@example.com',
        'token'           => TestCredentials::secret('stale'),
        'refresh_token'   => TestCredentials::secret('refresh'),
    ]);

    $result = app(SocialCallbackAction::class)->execute(
        request: callbackRequest('google'),
        guard: 'web',
    );

    expect($result->isPassed())->toBeTrue()
        ->and($result->user->getAuthIdentifier())->toBe($existingUser->getAuthIdentifier());
});

it('returns failed when socialite user has no email', function (): void {
    $noEmailUser = new SocialiteUser;
    $noEmailUser->map([
        'id'       => '123456789',
        'name'     => 'No Email',
        'nickname' => 'noemail',
    ]);
    $noEmailUser->token = 'mock-token';
    $noEmailUser->refreshToken = 'mock-refresh';

    Socialite::fake(SocialProvider::GOOGLE->value, $noEmailUser);

    $result = app(SocialCallbackAction::class)->execute(
        request: callbackRequest('google'),
        guard: 'web',
    );

    expect($result->isPassed())->toBeFalse();
});

it(description: 'raises a 404 rather than a 500 for an unknown provider slug', closure: function (): void {
    $action = app(abstract: SocialCallbackAction::class);

    expect(value: fn () => $action->execute(request: callbackRequest('myspace'), guard: 'web'))
        ->toThrow(exception: NotFoundHttpException::class);
});
