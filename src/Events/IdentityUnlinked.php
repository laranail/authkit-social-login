<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Social\Events;

/** Immutable audit metadata; never contains credentials or model instances. */
final readonly class IdentityUnlinked
{
    public function __construct(
        public string $userType,
        public string $userId,
        public string $provider,
    ) {}
}
