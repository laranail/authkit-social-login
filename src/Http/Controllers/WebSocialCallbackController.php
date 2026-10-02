<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Social\Http\Controllers;

use Illuminate\Http\Request;
use Simtabi\Laranail\AuthKit\Support\AuthKit;
use Simtabi\Laranail\AuthKit\Support\AuthResult;
use Simtabi\Laranail\AuthKit\Social\Support\SocialWebRoutes;

class WebSocialCallbackController extends AbstractSocialCallbackController
{
    protected function guard(): string
    {
        return (string) (SocialWebRoutes::currentMount()['guard'] ?? AuthKit::guard());
    }

    protected function passed(Request $request, AuthResult $result): mixed
    {
        return redirect()->intended(default: SocialWebRoutes::afterLoginRedirect());
    }

    protected function failed(Request $request, AuthResult $result): mixed
    {
        $route = SocialWebRoutes::currentMount()['name'] . 'login';

        return is_string($route) && \Illuminate\Support\Facades\Route::has($route)
            ? redirect()->to(route($route))->withErrors(provider: ['email' => 'Social authentication failed.'])
            : redirect()->to(config(key: 'authkit-social-login.web.failed_redirect', default: '/login'))
                ->withErrors(provider: ['email' => 'Social authentication failed.']);
    }
}
