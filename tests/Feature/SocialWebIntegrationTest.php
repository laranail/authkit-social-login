<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('registers standalone social web routes with package defaults', function (): void {
    expect(Route::has('laranail-social.social.redirect'))->toBeTrue()
        ->and(Route::has('laranail-social.social.callback'))->toBeTrue()
        ->and(Route::has('laranail-social.user-social-accounts.index'))->toBeTrue()
        ->and(Route::getRoutes()->match(Illuminate\Http\Request::create('/auth/social/google', 'GET'))->uri())
        ->toBe('auth/social/{provider}');
});

it('renders configured providers with credentials as social buttons', function (): void {
    config()->set('laranail.authkit-social-login.providers', ['google', 'apple']);
    config()->set('laranail.authkit-social-login.google.client_id', 'google-client');
    config()->set('laranail.authkit-social-login.apple.client_id', null);

    $view = $this->blade('<x-laranail-authkit-social-login::social-buttons />');

    $view->assertSee('Continue with Google')
        ->assertDontSee('Continue with Apple')
        ->assertSee('/auth/social/google');
});

it('accepts the POST callback Apple sends, and exempts it from CSRF', function (): void {
    // Apple requests the name and email scopes, which forces response_mode=form_post, so it POSTs
    // this endpoint from its own servers with no session and no CSRF token. A GET-only route
    // answers 405 and a CSRF-protected one answers 419; either kills Apple sign-in with nothing in
    // the log to explain it.
    $route = Route::getRoutes()->getByName('laranail-social.social.callback');

    // Asserted against the registered route rather than a response, because the CSRF middleware
    // skips validation outright while the suite runs, so a 419 can never be observed here and
    // asserting on the status code would pin nothing.
    expect($route)->not->toBeNull()
        ->and($route->methods())->toContain('POST')
        ->and($route->methods())->toContain('GET')
        ->and($route->excludedMiddleware())->not->toBeEmpty();
});

it('404s an unknown provider rather than throwing', function (): void {
    // Same answer as the API surface, and deliberately not a redirect to the login page: a 404
    // does not confirm which providers this installation has configured.
    $this->post('/auth/social/myspace/callback')->assertNotFound();
});
