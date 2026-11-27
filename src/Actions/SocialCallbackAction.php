<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Social\Actions;

use Illuminate\Http\Request;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Socialite\Two\InvalidStateException;
use Simtabi\Laranail\AuthKit\Support\AuthResult;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Simtabi\Laranail\AuthKit\Social\Support\ResolvesIdentityProvider;
use Simtabi\Laranail\AuthKit\Social\Contracts\SocialCallbackActionInterface;
use Simtabi\Laranail\AuthKit\Social\Contracts\ResolveSocialIdentityInterface;

class SocialCallbackAction implements SocialCallbackActionInterface
{
    use ResolvesIdentityProvider;

    public function __construct(
        private SocialiteFactory $socialite,
        private ResolveSocialIdentityInterface $resolver,
        private \Simtabi\Laranail\AuthKit\Contracts\IdentityProviderRegistryInterface $providers,
    ) {}

    public function execute(Request $request, string $guard): AuthResult
    {
        $provider = $this->resolveProvider(request: $request);

        if ($request->filled('error')) {
            return AuthResult::failed();
        }
        try {
            $socialiteUser = $this->socialite->driver($provider->driver())->user();
        } catch (InvalidStateException|ClientException) {
            return AuthResult::failed();
        }

        $providerId = $socialiteUser->getId();

        if ((! is_string($providerId) && ! is_int($providerId)) || trim((string) $providerId) === '') {
            return AuthResult::failed();
        }

        $user = $this->resolver->execute(
            provider: $provider,
            socialUser: $socialiteUser,
            guard: $guard,
        );

        if (! $user instanceof Authenticatable) {
            return AuthResult::failed();
        }

        return AuthResult::passed($user);
    }
}
