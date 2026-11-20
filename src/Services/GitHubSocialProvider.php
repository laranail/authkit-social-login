<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Social\Services;

use Laravel\Socialite\Two\GithubProvider;
use SocialiteProviders\Manager\ConfigTrait;

/** Expose Socialite's verified-primary-email selection to AuthKit's identity resolver. */
class GitHubSocialProvider extends GithubProvider
{
    use ConfigTrait;

    protected function mapUserToObject(array $user)
    {
        // With user:email, Socialite replaces the public profile email with the verified primary
        // address from /user/emails (or null on failure). Without that scope it is untrusted.
        $user['email_verified'] = in_array('user:email', $this->scopes, true)
            && is_string($user['email'] ?? null)
            && filter_var($user['email'], FILTER_VALIDATE_EMAIL) !== false;

        return parent::mapUserToObject($user);
    }
}
