<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Social\Casts;

use Illuminate\Database\Eloquent\Model;
use Simtabi\Laranail\AuthKit\Social\Enums\SocialProvider;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Simtabi\Laranail\AuthKit\Contracts\SocialIdentityProviderInterface;
use Simtabi\Laranail\AuthKit\Contracts\IdentityProviderRegistryInterface;

/**
 * Reads a stored provider slug back as whatever kind of provider it is.
 *
 * `provider` used to cast straight to the SocialProvider enum, which meant a provider contributed
 * through the identity-provider registry could not be stored at all: reading the row threw
 * `ValueError: "okta" is not a valid backing value for enum SocialProvider`. The registry could
 * register a provider, render its button and bind its Socialite driver, and then the first person to
 * actually sign in with it hit that. The seam worked right up to the point of being used.
 *
 * A slug is resolved through the enum first and the registry second — the same order the request
 * path uses, so a registration can never shadow a built-in.
 *
 * An unresolvable slug comes back as the plain string rather than throwing. That case is real: a
 * sub-package can be removed while its rows remain, and a stored link whose provider is no longer
 * installed should still be listable and unlinkable rather than making the account unreadable.
 *
 * @implements CastsAttributes<SocialIdentityProviderInterface|string|null, SocialIdentityProviderInterface|string|null>
 */
class IdentityProviderCast implements CastsAttributes
{
    /** @param array<string, mixed> $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): SocialIdentityProviderInterface|string|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        $slug = (string) $value;

        return SocialProvider::tryFrom($slug)
            // @facade-call-exempt: Eloquent constructs casts outside container autowiring; resolve the current contributed-provider registry at cast time.
            ?? app(abstract: IdentityProviderRegistryInterface::class)->get($slug)
            ?? $slug;
    }

    /** @param array<string, mixed> $attributes */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof SocialIdentityProviderInterface
            ? $value->slug()
            : (string) $value;
    }
}
