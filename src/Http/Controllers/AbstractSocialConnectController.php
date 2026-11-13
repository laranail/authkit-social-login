<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Social\Http\Controllers;

use Illuminate\Http\Request;
use Simtabi\Laranail\AuthKit\Social\Support\SocialWebRoutes;
use Simtabi\Laranail\AuthKit\Exceptions\IdentityLinkException;
use Simtabi\Laranail\AuthKit\Http\Controllers\AbstractAuthController;
use Simtabi\Laranail\AuthKit\Social\Contracts\ConnectSocialAccountInterface;

/**
 * The two legs of connecting a provider to the signed-in account.
 *
 * Both routes sit behind `auth`, unlike the sign-in pair which sits behind `guest`. That is the
 * whole distinction the issue asks for: connect intent is declared by which endpoint you reach,
 * never inferred from the fact that somebody happens to be logged in.
 *
 * Neither leg signs anyone in. A refusal comes back as a validation error on the page the user
 * started from, with the reason taken from a closed enum rather than a message.
 */
abstract class AbstractSocialConnectController extends AbstractAuthController
{
    public function start(Request $request, ConnectSocialAccountInterface $connect): mixed
    {
        try {
            $result = $connect->start(
                request: $request,
                user: $request->user($this->guard()),
                guard: $this->guard(),
                callbackUrl: $this->callbackUrl($request),
            );
        } catch (IdentityLinkException $exception) {
            return $this->refused(request: $request, exception: $exception);
        }

        if ($request->expectsJson()) {
            return $this->jsonResponse(status: 'passed', data: ['url' => $result->url, 'link_token' => $result->token]);
        }

        return redirect()->to(path: $result->url);
    }

    public function callback(Request $request, ConnectSocialAccountInterface $connect): mixed
    {
        try {
            $social = $connect->complete(
                request: $request,
                user: $request->user($this->guard()),
                guard: $this->guard(),
                callbackUrl: $this->callbackUrl($request),
            );
        } catch (IdentityLinkException $exception) {
            return $this->refused(request: $request, exception: $exception);
        }

        if ($request->expectsJson()) {
            return $this->jsonResponse(status: 'passed', data: ['provider' => $social->provider]);
        }

        return redirect()->to(path: $this->accountsUrl())->with('status', 'social-account-connected');
    }

    /**
     * The connect callback's own URL, which is not the sign-in callback's.
     *
     * It is the `redirect_uri` the provider records on the authorize call and checks again on the
     * token exchange, so both legs have to name the same one.
     */
    protected function callbackUrl(Request $request): string
    {
        return route(
            SocialWebRoutes::currentMount()['name'] . 'social.connect.callback',
            ['provider' => (string) $request->route('provider')],
        );
    }

    protected function accountsUrl(): string
    {
        return route(SocialWebRoutes::currentMount()['name'] . 'user-social-accounts.index');
    }

    protected function refused(Request $request, IdentityLinkException $exception): mixed
    {
        if ($request->expectsJson()) {
            return $this->jsonResponse(
                status: 'failed',
                data: ['reason' => $exception->failure->value, 'message' => $exception->getMessage()],
                code: 422,
            );
        }

        return redirect()->to(path: $this->accountsUrl())
            ->withErrors(provider: ['provider' => $exception->getMessage()]);
    }
}
