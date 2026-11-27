# Environment variables

These names are read by this package's shipped configuration. Set deployment values in the host
application's environment; do not edit files under `vendor/`. Defaults and corresponding config
keys are explained in [Configuration](configuration.md).

| Variable |
| --- |
| `AUTHKIT_SOCIAL_ENABLED` |
| `AUTHKIT_SOCIAL_IMPLICIT_LINKING` |
| `AUTHKIT_SOCIAL_UNLINK_TRUSTS_PASSWORD` |
| `AUTHKIT_SOCIAL_API_ENABLED` |
| `AUTHKIT_SOCIAL_WEB_ENABLED` |
| `AUTHKIT_SOCIAL_GUARD` |
| `AUTHKIT_PRESET_GUARD` |
| `AUTHKIT_SOCIAL_WEB_PREFIX` |
| `AUTHKIT_PRESET_WEB_PREFIX` |
| `AUTHKIT_SOCIAL_ROUTE_NAME_PREFIX` |
| `AUTHKIT_SOCIAL_AFTER_LOGIN` |
| `AUTHKIT_PRESET_AFTER_SOCIAL_LOGIN` |
| `AUTHKIT_SOCIAL_FAILED_REDIRECT` |
| `AUTHKIT_SOCIAL_ROUTES_MODE` |
| `AUTHKIT_GOOGLE_CLIENT_ID` |
| `AUTHKIT_GOOGLE_CLIENT_SECRET` |
| `AUTHKIT_GOOGLE_REDIRECT` |
| `AUTHKIT_APPLE_CLIENT_ID` |
| `AUTHKIT_APPLE_CLIENT_SECRET` |
| `AUTHKIT_APPLE_REDIRECT` |
| `AUTHKIT_X_CLIENT_ID` |
| `AUTHKIT_X_CLIENT_SECRET` |
| `AUTHKIT_X_REDIRECT` |
| `AUTHKIT_LINKEDIN_CLIENT_ID` |
| `AUTHKIT_LINKEDIN_CLIENT_SECRET` |
| `AUTHKIT_LINKEDIN_REDIRECT` |
| `AUTHKIT_PAYPAL_CLIENT_ID` |
| `AUTHKIT_PAYPAL_CLIENT_SECRET` |
| `AUTHKIT_PAYPAL_REDIRECT` |
| `AUTHKIT_PAYPAL_SANDBOX_MODE` |
| `AUTHKIT_GITHUB_CLIENT_ID` |
| `AUTHKIT_GITHUB_CLIENT_SECRET` |
| `AUTHKIT_GITHUB_REDIRECT` |
| `AUTHKIT_FACEBOOK_CLIENT_ID` |
| `AUTHKIT_FACEBOOK_CLIENT_SECRET` |
| `AUTHKIT_FACEBOOK_REDIRECT` |

## Apply changes

```bash
php artisan config:clear
php artisan route:list
```

Check the configured route prefix and guard, then rebuild the configuration cache as part of your
deployment. Never commit filled provider-secret or signing-key variables. A variable mentioned by
a different package does not configure this package unless explicitly mapped here.

---

[← Docs index](../README.md#documentation)
