<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\Enumerator\Rules\EnumValue;
use Simtabi\Laranail\AuthKit\Support\IdentityProvider;
use Simtabi\Laranail\AuthKit\Social\Enums\SocialProvider;
use Simtabi\Laranail\AuthKit\Social\Actions\SocialRedirectAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Simtabi\Laranail\AuthKit\Social\Support\ResolvesIdentityProvider;
use Simtabi\Laranail\AuthKit\Contracts\IdentityProviderRegistryInterface;

function redirectRequest(string $provider): Request
{
    $request = Request::create(uri: "/auth/social/{$provider}", method: 'GET');
    $request->setRouteResolver(fn () => (new Route('GET', '/auth/social/{provider}', []))->bind($request));
    $request->route()->setParameter('provider', $provider);

    return $request;
}

it(description: 'returns redirect url for google', closure: function (): void {
    Socialite::fake(driver: SocialProvider::GOOGLE->driver());

    $action = app(abstract: SocialRedirectAction::class);

    $result = $action->execute(request: redirectRequest('google'));

    expect(value: $result->url)->toBeString()->not->toBeEmpty();
});

it(description: 'returns redirect url for apple', closure: function (): void {
    Socialite::fake(driver: SocialProvider::APPLE->driver());

    $action = app(abstract: SocialRedirectAction::class);

    $result = $action->execute(request: redirectRequest('apple'));

    expect(value: $result->url)->toBeString()->not->toBeEmpty();
});

it(description: 'returns redirect url for X', closure: function (): void {
    Socialite::fake(driver: SocialProvider::X->driver());

    $action = app(abstract: SocialRedirectAction::class);

    $result = $action->execute(request: redirectRequest('x'));

    expect(value: $result->url)->toBeString()->not->toBeEmpty();
});

it(description: 'returns redirect url for linkedin', closure: function (): void {
    Socialite::fake(driver: SocialProvider::LINKEDIN->driver());

    $action = app(abstract: SocialRedirectAction::class);

    $result = $action->execute(request: redirectRequest('linkedin'));

    expect(value: $result->url)->toBeString()->not->toBeEmpty();
});

it(description: 'returns redirect url for paypal', closure: function (): void {
    Socialite::fake(driver: SocialProvider::PAYPAL->driver());

    $action = app(abstract: SocialRedirectAction::class);

    $result = $action->execute(request: redirectRequest('paypal'));

    expect(value: $result->url)->toBeString()->not->toBeEmpty();
});

it('exposes Enumerator labels and collection helpers without changing provider values', function (): void {
    expect(SocialProvider::values())->toBe([
        'google',
        'apple',
        'x',
        'linkedin',
        'paypal',
        'github',
        'facebook',
    ])
        ->and(SocialProvider::labels())->toBe([
            'google'   => 'Google',
            'apple'    => 'Apple',
            'x'        => 'X',
            'linkedin' => 'LinkedIn',
            'paypal'   => 'PayPal',
            'github'   => 'GitHub',
            'facebook' => 'Facebook',
        ])
        ->and(SocialProvider::collect()->flatValues())->toBe(SocialProvider::values())
        ->and(SocialProvider::GOOGLE->label())->toBe('Google');
});

it('validates social provider values with Enumerator', function (): void {
    expect(Validator::make(
        data: ['provider' => 'google'],
        rules: ['provider' => [new EnumValue(SocialProvider::class)]],
    )->passes())->toBeTrue()
        ->and(Validator::make(
            data: ['provider' => 'myspace'],
            rules: ['provider' => [new EnumValue(SocialProvider::class)]],
        )->fails())->toBeTrue();
});

it(description: 'raises a 404 rather than a 500 for an unknown provider slug', closure: function (): void {
    $action = app(abstract: SocialRedirectAction::class);

    expect(value: fn () => $action->execute(request: redirectRequest('myspace')))
        ->toThrow(exception: NotFoundHttpException::class);
});

it(description: 'redirects for a provider a sub-package registered, without editing this package', closure: function (): void {
    app(abstract: IdentityProviderRegistryInterface::class)->register(
        new IdentityProvider(
            slug: 'okta',
            label: 'Okta',
            assertsEmailVerified: true,
        ),
    );
    Socialite::fake(driver: 'okta');

    $result = app(abstract: SocialRedirectAction::class)->execute(request: redirectRequest('okta'));

    expect(value: $result->url)->toBeString()->not->toBeEmpty();
});

it(description: 'does not let a registration shadow a built-in provider', closure: function (): void {
    // A package registering 'google' must not take over Google sign-in: the enum's verification
    // rules would stop applying to it, and an IdentityProvider can declare itself verified with
    // no exhaustive match to answer to.
    app(abstract: IdentityProviderRegistryInterface::class)->register(
        new IdentityProvider(
            slug: 'google',
            label: 'Not Google',
            assertsEmailVerified: true,
        ),
    );

    $request = redirectRequest('google');
    $resolved = (new class
    {
        use ResolvesIdentityProvider;

        public function resolve($r)
        {
            return $this->resolveProvider($r);
        }
    })->resolve($request);

    expect(value: $resolved)->toBe(SocialProvider::GOOGLE);
});
