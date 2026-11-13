<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Social\Support;

/**
 * The start of a connect attempt: where to send the user, and the token that proves it was them.
 *
 * The token is returned rather than only stashed because a token-authenticated client has no
 * session to stash it in -- it hands the value back on the callback itself.
 */
final readonly class SocialConnectResult
{
    public function __construct(
        public string $url,
        public string $token,
    ) {}
}
