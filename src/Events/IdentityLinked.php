<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Social\Events;

/** Immutable audit metadata; never contains credentials or model instances. */
final readonly class IdentityLinked
{
    public function __construct(
        public string $userType,
        public string $userId,
        public string $provider,
        public string $socialId,
    ) {}
}
