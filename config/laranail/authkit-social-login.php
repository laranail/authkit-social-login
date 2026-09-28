<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | DEFAULTS TRUE, UNLIKE EVERY OTHER AUTHKIT SIBLING, AND THE DIFFERENCE IS
    | DELIBERATE. authkit-social-login, -ldap, -oauth and -tenancy default to false
    | because they are inert placeholders: installing one must not change how an
    | application authenticates.
    |
    | This package is not that. It carries social login that used to live inside
    | laranail/authkit, so an application upgrading across the extraction has it
    | already configured and already in use. Defaulting to false would switch
    | social login off during a routine `composer update`, with no error and
    | nothing in the logs -- the buttons would simply stop appearing.
    |
    | Set AUTHKIT_SOCIAL_ENABLED=false to turn it off deliberately.
    |
    */

    'enabled' => (bool) env(key: 'AUTHKIT_SOCIAL_ENABLED', default: true),

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    | Moved here from laranail.authkit.social when social login left the core.
    |
    | THE ENV VARIABLE NAMES ARE UNCHANGED ON PURPOSE. They sit in deployed .env
    | files; renaming them would break every existing installation silently, with
    | credentials simply resolving to null. Only the config KEY moved.
    |
    | SocialServiceProvider copies each block into Laravel's own `services.*`,
    | which is where Socialite reads credentials from.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Social sign-in over the API
    |--------------------------------------------------------------------------
    |
    | Off by default: it widens the authentication surface, and an application that only serves a
    | browser has no use for it. Turn it on for a SPA or native client.
    |
    | The flow is authorization-code, not token-exchange. The client asks for a URL, opens it, and
    | posts back the code; this package performs the exchange with its own client secret. Accepting
    | an access token the client already holds is deliberately not offered -- a provider's userinfo
    | response carries no audience claim, so a token minted for another application cannot be told
    | apart from one minted for this one.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Unlinking a provider
    |--------------------------------------------------------------------------
    |
    | Removing a linked provider is the one social operation that can lock someone out permanently,
    | so by default the last remaining link cannot be removed.
    |
    | The obvious alternative -- allow it when the user has a password -- does not work here. Social
    | provisioning writes Hash::make(Str::random(32)), a password the user has never seen, so the
    | column is populated for exactly the accounts most at risk and nothing in a hash tells a chosen
    | password from a generated one.
    |
    | An application that records whether a password was actually chosen can answer that question and
    | set this to true. Everyone else should leave it alone: a user who wants to remove their only
    | provider sets a password first through the ordinary reset flow.
    |
    */

    'unlink' => [
        'trust_password_column' => (bool) env(key: 'AUTHKIT_SOCIAL_UNLINK_TRUSTS_PASSWORD', default: false),
    ],

    'api' => [
        'enabled' => (bool) env(key: 'AUTHKIT_SOCIAL_API_ENABLED', default: false),
    ],

    'google' => [
        'client_id'     => env(key: 'AUTHKIT_GOOGLE_CLIENT_ID'),
        'client_secret' => env(key: 'AUTHKIT_GOOGLE_CLIENT_SECRET'),
        'redirect'      => env(key: 'AUTHKIT_GOOGLE_REDIRECT'),
        'scopes'        => ['openid', 'profile', 'email'],

        /*
         * Optional parameters passed to the provider on the redirect. Google reads `hd` to restrict
         * sign-in to one Workspace domain and `prompt` to force account selection. Do not pass
         * reserved keys such as `state` or `response_type`.
         */
        'with' => [],
    ],

    /*
     * Apple's client_secret is not a static string: it is a short-lived ES256 JWT signed
     * with the .p8 key from your Apple developer account, and Apple caps its lifetime at
     * six months. Generate it out of band and rotate it before it expires, or every Apple
     * sign-in starts failing on a date nothing in this repository records.
     *
     * The client_id is the Services ID, not the App ID.
     */
    'apple' => [
        'client_id'     => env(key: 'AUTHKIT_APPLE_CLIENT_ID'),
        'client_secret' => env(key: 'AUTHKIT_APPLE_CLIENT_SECRET'),
        'redirect'      => env(key: 'AUTHKIT_APPLE_REDIRECT'),
        'scopes'        => ['name', 'email'],
    ],

    /*
     * The driver key is `x`, which resolves straight to Socialite's OAuth 2 XProvider.
     *
     * The legacy `twitter` key is deliberately not used: SocialiteManager::createTwitterDriver()
     * falls back to an OAuth 1.0a provider unless the config carries `oauth => 2`, and that
     * provider wants `identifier`/`secret` rather than `client_id`/`client_secret` -- it throws
     * before it ever redirects, and never returns `confirmed_email`.
     *
     * `confirmed_email` only arrives when "Request email from users" is enabled on the app in X's
     * developer dashboard. Without it the address is absent and no X login can link.
     */
    'x' => [
        'client_id'     => env(key: 'AUTHKIT_X_CLIENT_ID'),
        'client_secret' => env(key: 'AUTHKIT_X_CLIENT_SECRET'),
        'redirect'      => env(key: 'AUTHKIT_X_REDIRECT'),
        'scopes'        => ['users.read', 'users.email', 'tweet.read'],
    ],

    /*
     * The slug stays `linkedin` because it is stored data, but SocialProvider::LINKEDIN->driver()
     * resolves to `linkedin-openid` and this block is published under that key. Socialite's
     * `linkedin` driver is the legacy API, whose projection carries no `email_verified` at all.
     */
    'linkedin' => [
        'client_id'     => env(key: 'AUTHKIT_LINKEDIN_CLIENT_ID'),
        'client_secret' => env(key: 'AUTHKIT_LINKEDIN_CLIENT_SECRET'),
        'redirect'      => env(key: 'AUTHKIT_LINKEDIN_REDIRECT'),
        'scopes'        => ['openid', 'profile', 'email'],
    ],

    'paypal' => [
        'client_id'     => env(key: 'AUTHKIT_PAYPAL_CLIENT_ID'),
        'client_secret' => env(key: 'AUTHKIT_PAYPAL_CLIENT_SECRET'),
        'redirect'      => env(key: 'AUTHKIT_PAYPAL_REDIRECT'),
        'sandbox_mode'  => (bool) env(key: 'AUTHKIT_PAYPAL_SANDBOX_MODE', default: true),
        'scopes'        => ['openid', 'profile', 'email'],
    ],

];
