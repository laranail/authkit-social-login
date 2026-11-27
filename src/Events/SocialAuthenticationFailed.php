<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Social\Events;

/** Immutable audit metadata; never contains credentials or model instances. */
final readonly class SocialAuthenticationFailed
{
    public function __construct(
        public string $provider,
        public string $guard,
        public string $surface,
    ) {}
}
