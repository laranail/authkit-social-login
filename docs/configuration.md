# Configuration

Package settings live under `authkit-social-login`. Publish them with:

```bash
php artisan vendor:publish --tag=laranail::authkit-social-login-config
```

## Main settings

| Key | Type | Default | Purpose |
|---|---|---|---|
| `enabled` | bool | `true` | Enables social package behavior. |
| `providers` | string array | `['google']` | Provider slugs shown by the button component. |
| `ui` | array | `[]` | Per-provider button label, icon, CSS class, and ordering overrides. |
| `api.enabled` | bool | `false` | Registers stateless API social sign-in endpoints. |
| `unlink.trust_password_column` | bool | `false` | Allows unlinking the last provider when the application knows a password was chosen. |

## Web routes

| Key | Type | Default | Purpose |
|---|---|---|---|
| `web.enabled` | bool | `true` | Enables browser social routes and account management. |
| `web.guard` | string | `web` | Standalone authentication guard. |
| `web.prefix` | string | `auth` | Standalone URL prefix. |
| `web.route_name_prefix` | string | `laranail-social.` | Standalone route-name prefix. |
| `web.middleware` | string array | `['web']` | Middleware for standalone routes. |
| `web.after_login` | string | `/dashboard` | Successful sign-in destination. |
| `web.failed_redirect` | string | `/login` | Failed sign-in destination. |
| `web.routes_mode` | string | `package` | Use package routes or load a published route file (`published`). |

When `authkit-preset` is installed and configured, the social routes inherit its route mounts,
guards, middleware, and route-name prefixes. These settings apply when the social package runs
independently.

## Provider configuration

Each provider block carries its Socialite credentials and supported scopes. Credentials are read
from the existing `AUTHKIT_<PROVIDER>_*` environment variables and copied into Laravel's
`services.*` config for Socialite. The installer can select providers and append callback URLs to
`.env`:

```bash
php artisan laranail::authkit-social-login.install --social=google --social=apple
```

See [social login](social-login.md) for all supported providers, credential names, and callback
requirements.

## Overriding

`mergeConfigFrom` provides defaults beneath the application's published config. Edit the published
file to override settings. Laravel's config merge is shallow, so replace nested arrays such as
`web` or `ui` as a whole when customizing them.

---

[← Docs index](../README.md#documentation)
