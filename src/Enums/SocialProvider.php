<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Social\Enums;

use Simtabi\Laranail\Enumerator\Attributes\Label;
use Simtabi\Laranail\Enumerator\Contracts\Enumerator;
use Simtabi\Laranail\Enumerator\Concerns\HasEnumerator;
use Simtabi\Laranail\AuthKit\Contracts\SocialIdentityProviderInterface;

enum SocialProvider: string implements Enumerator, SocialIdentityProviderInterface
{
    use HasEnumerator;

    #[Label('Google')]
    case GOOGLE = 'google';

    #[Label('Apple')]
    case APPLE = 'apple';

    #[Label('X')]
    case X = 'x';

    #[Label('LinkedIn')]
    case LINKEDIN = 'linkedin';

    #[Label('PayPal')]
    case PAYPAL = 'paypal';

    #[Label('GitHub')]
    case GITHUB = 'github';

    #[Label('Facebook')]
    case FACEBOOK = 'facebook';

    /**
     * Whether this provider asserts that it verified the address it returns.
     *
     * Only a provider that asserts verification may auto-link a social login to an
     * existing account by email address. One that does not must never link, because
     * anyone able to register an account carrying someone else's address would
     * otherwise take over that account.
     *
     * Facebook does not assert email verification. It supports explicit linking to an
     * authenticated account and subsequent sign-in by provider ID, never email matching.
     */
    public function assertsEmailVerified(): bool
    {
        return match ($this) {
            self::GOOGLE, self::APPLE, self::LINKEDIN, self::PAYPAL, self::X, self::GITHUB => true,
            self::FACEBOOK                                                                 => false,
        };
    }

    /**
     * Whether this provider's raw payload asserts the address was verified.
     *
     * The claim differs in name and in shape, so each provider reads its own. The
     * OIDC-style providers return a boolean `email_verified`, which may arrive as a
     * real boolean or as the string "true"/"false" — Apple routinely sends the string
     * form. X instead returns the confirmed address itself as `confirmed_email` and
     * omits it entirely when the address is unconfirmed, so its presence as a valid
     * address is the assertion.
     *
     * Apple may return a per-app relay address on `@privaterelay.appleid.com`. Apple
     * verifies those, so they are trusted here; they simply will not match a local
     * account, so in practice they provision rather than link.
     *
     * The match is deliberately exhaustive: a new case on this enum fails loudly
     * here rather than defaulting to either answer.
     *
     * @param array<string, mixed> $rawUser
     */
    public function hasVerifiedEmail(array $rawUser): bool
    {
        return match ($this) {
            self::GOOGLE, self::APPLE, self::LINKEDIN, self::PAYPAL, self::GITHUB => filter_var(
                $rawUser['email_verified'] ?? false,
                FILTER_VALIDATE_BOOLEAN,
            ),
            self::FACEBOOK => false,
            self::X        => is_string($rawUser['confirmed_email'] ?? null)
                && filter_var($rawUser['confirmed_email'], FILTER_VALIDATE_EMAIL) !== false,
        };
    }

    /**
     * The route value, and what is stored against a linked social account.
     *
     * Named slug() rather than reusing ->value so the enum and an externally contributed
     * IdentityProvider answer the same question through the same interface.
     */
    public function slug(): string
    {
        return $this->value;
    }

    /**
     * The Socialite driver key, which is not always the slug.
     *
     * LinkedIn is the case that matters: the `linkedin` key resolves to Socialite's legacy provider,
     * whose projection is `id, firstName, lastName, profilePicture` with no `email_verified` in it at
     * all. Reading a claim that is never sent means the provider completes the OAuth round trip and
     * then silently refuses to link or provision. `linkedin-openid` projects
     * `sub,email,email_verified,...` and is the one to use.
     *
     * The match is exhaustive for the same reason as the others: a new case has to answer.
     */
    public function driver(): string
    {
        return match ($this) {
            self::GOOGLE, self::APPLE, self::X, self::PAYPAL, self::GITHUB, self::FACEBOOK => $this->value,
            self::LINKEDIN                                                                 => 'linkedin-openid',
        };
    }

    // label() is not declared here: HasEnumerator already provides it from the #[Label]
    // attributes, and a method declared on the class would take precedence over the trait's --
    // silently replacing a working implementation with a broken one.
}
