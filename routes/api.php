<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Simtabi\Laranail\AuthKit\Support\AuthKit;
use Simtabi\Laranail\AuthKit\Social\Support\SocialConfig;
use Simtabi\Laranail\AuthKit\Social\Http\Controllers\ApiSocialController;

/*
|--------------------------------------------------------------------------
| Social sign-in for clients with no session
|--------------------------------------------------------------------------
|
| Mounted under the core's API prefix so a client sees one API. Two steps, because the
| authorization-code flow needs two: the client asks for a URL, opens it, and posts back the code.
|
| The redirect URI registered with the provider must be one the client can receive -- a custom URL
| scheme for a native app, or a page in the SPA -- and must match `redirect` in this package's
| config, because the provider checks it again at the exchange.
|
| Accepting a provider access token the client already holds is deliberately not offered. See
| StatelessSocialCallback for why: the userinfo response carries no audience claim, so a token
| minted for another application cannot be told apart from one minted for this one.
|
*/

if (! (bool) SocialConfig::get('api.enabled', false)) {
    return;
}

Route::prefix(AuthKit::apiPrefix())
    ->middleware(AuthKit::apiMiddleware())
    ->name(AuthKit::apiRouteNamePrefix())
    ->group(function (): void {
        Route::get('/social/{provider}/redirect', [ApiSocialController::class, 'redirect'])
            ->middleware('throttle:20,1')
            ->name('social.redirect');

        Route::post('/social/{provider}/callback', [ApiSocialController::class, 'callback'])
            ->middleware('throttle:10,1')
            ->name('social.callback');
    });
