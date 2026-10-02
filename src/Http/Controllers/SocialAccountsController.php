<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Social\Http\Controllers;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Simtabi\Laranail\AuthKit\Social\Enums\SocialProvider;
use Simtabi\Laranail\AuthKit\Social\Services\SocialAccountService;
use Simtabi\Laranail\AuthKit\Social\Support\SocialProviders;
use Simtabi\Laranail\AuthKit\Contracts\IdentityProviderRegistryInterface;
use Simtabi\Laranail\AuthKit\Social\Contracts\UnlinkSocialAccountInterface;

class SocialAccountsController
{
    public function index(Request $request, SocialAccountService $accounts): View
    {
        $user = $request->user();

        $linkedAccounts = $accounts->forUser($user)->map(fn ($social): array => [
            'slug'       => is_string($social->provider) ? $social->provider : $social->provider->slug(),
            'label'      => is_string($social->provider) ? $social->provider : $social->provider->label(),
            'email'      => $social->email,
            'icon'       => 'laranail/authkit-social-login::icons.' . (is_string($social->provider) ? $social->provider : $social->provider->slug()),
            'can_unlink' => ! is_string($social->provider) && $accounts->canUnlink($user, $social->provider),
        ]);
        $linkedSlugs = $linkedAccounts->pluck('slug')->all();

        return view('laranail/authkit-social-login::social-accounts', [
            'accounts' => $linkedAccounts,
            'supportedProviders' => collect(SocialProviders::buttons())
                ->map(fn (array $provider): array => [
                    'slug' => $provider['slug'],
                    'label' => $provider['label'],
                    'icon' => $provider['icon'],
                    'connected' => in_array($provider['slug'], $linkedSlugs, true),
                ])
                ->values(),
        ]);
    }

    public function destroy(
        Request $request,
        string $provider,
        UnlinkSocialAccountInterface $unlink,
        IdentityProviderRegistryInterface $registry,
    ): RedirectResponse {
        $resolved = SocialProvider::tryFrom($provider) ?? $registry->get($provider);

        if ($resolved === null) {
            abort(404);
        }

        if (! $unlink->execute(user: $request->user(), provider: $resolved)) {
            return back()->withErrors([
                'provider' => 'That is the only way you can sign in, so it cannot be removed. Add another sign-in method first.',
            ]);
        }

        return back()->with('status', 'social-account-unlinked');
    }
}
