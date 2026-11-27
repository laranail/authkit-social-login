<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Social\Support;

use Illuminate\Http\Request;
use Simtabi\Laranail\AuthKit\Social\Enums\SocialProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Simtabi\Laranail\AuthKit\Contracts\SocialIdentityProviderInterface;

/**
 * Turns the `{provider}` route parameter into a provider, whoever supplied it.
 *
 * Built-in providers are enum cases; a sub-package such as laranail/authkit-sso contributes its
 * own through the registry. The enum is checked first so a built-in can never be shadowed by a
 * registration -- otherwise a package could quietly take over Google sign-in by registering that
 * slug, and the enum's exhaustive verification rules would stop applying to it.
 *
 * An unrecognised slug is a 404 rather than the uncaught ValueError that SocialProvider::from()
 * raises, because the value comes straight off the URL.
 */
trait ResolvesIdentityProvider
{
    protected function resolveProvider(Request $request): SocialIdentityProviderInterface
    {
        $slug = (string) $request->route('provider');

        $provider = SocialProvider::tryFrom($slug)
            ?? $this->providers->get($slug);

        if ($provider === null) {
            throw new NotFoundHttpException(message: __('laranail/authkit-social-login::messages.unknown_provider'));
        }

        return $provider;
    }
}
