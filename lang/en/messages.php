<?php

declare(strict_types=1);

return [
    'unknown_provider'                 => 'Unknown social provider.',
    'sign_in_failed'                   => 'That social account could not be used to sign in.',
    'authentication_failed'            => 'Social authentication failed.',
    'only_sign_in_method_error'        => 'That is the only way you can sign in, so it cannot be removed. Add another sign-in method first.',
    'social_accounts'                  => 'Social Accounts',
    'add_another_sign_in_method_first' => 'Add another sign-in method first',
    'continue_with_provider'           => 'Continue with :provider',
    'provider_count'                   => '{1} :count provider|[0,*] :count providers',
    'accounts_description'             => 'Review the social providers available for sign-in and the accounts linked to your profile.',
    'account_connected'                => 'The account was connected.',
    'account_disconnected'             => 'The account was disconnected.',
    'supported_providers'              => 'Supported providers',
    'providers_description'            => 'Providers configured by this application.',
    'connected'                        => 'Connected',
    'connect'                          => 'Connect',
    'linked_social_accounts'           => 'Linked social accounts',
    'linked_accounts_description'      => 'These accounts can be used to sign in.',
    'no_social_accounts_linked'        => 'No social accounts linked',
    'no_linked_accounts_description'   => 'Linked social accounts will appear here when available.',
    'no_email_address_provided'        => 'No email address provided',
    'disconnect'                       => 'Disconnect',
    'only_sign_in_method'              => 'Only sign-in method',
    'only_sign_in_method_help'         => 'Your only sign-in method cannot be disconnected. Add another sign-in method first.',
    'installer_routes'                 => 'Set AUTHKIT_SOCIAL_ROUTES_MODE=published and load routes/laranail-authkit-social-login-web.php from the application route bootstrap.',
    'installer_next_steps'             => 'Review config/laranail/authkit-social-login.php and run php artisan migrate.',
    'installer_providers_configured'   => 'Social login configured for: :providers.',
    'installer_choose_providers'       => 'Which social login providers would you like to enable?',
];
