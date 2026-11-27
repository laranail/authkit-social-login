# laranail/authkit-social-login

[![Tests](https://github.com/laranail/authkit-social-login/actions/workflows/tests.yml/badge.svg)](https://github.com/laranail/authkit-social-login/actions/workflows/tests.yml)
[![Code style](https://github.com/laranail/authkit-social-login/actions/workflows/code-style.yml/badge.svg)](https://github.com/laranail/authkit-social-login/actions/workflows/code-style.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

> Socialite-backed sign-in, explicit identity linking and account provisioning for AuthKit.

Requires PHP `^8.4.1 || ^8.5` and Laravel `^13.0`. This package is pre-1.0; review
[feature scope](docs/feature-scope.md) before choosing the flows to enable.

## Install

`laranail/*` packages resolve through git, not Packagist. Add the repositories block to your
application's `composer.json`:

```json
"repositories": [
    {"type": "vcs", "url": "https://github.com/laranail/authkit-social-login.git"},
    {"type": "vcs", "url": "https://github.com/laranail/authkit.git"},
    {"type": "vcs", "url": "https://github.com/laranail/console.git"},
    {"type": "vcs", "url": "https://github.com/laranail/enumerator.git"},
    {"type": "vcs", "url": "https://github.com/laranail/package-tools.git"},
    {"type": "composer", "url": "https://repo.packagist.org", "exclude": ["laranail/*"]},
    {"packagist.org": false}
]
```

Then require the social package explicitly:

```bash
composer require laranail/authkit-social-login
```

The package is enabled by default. Run its installer to publish the config and the migration and
to add provider credential variables to `.env`, then create the `socials` table. The migration is
published even when you have not selected a provider yet, because connected-account management
needs the table:

```bash
php artisan laranail::authkit-social-login.install --social=google
php artisan migrate
```

To publish the migration without the installer, use its tag directly:
`php artisan vendor:publish --tag=laranail::authkit-social-login-migrations`.

Fill in `AUTHKIT_GOOGLE_CLIENT_ID` and `AUTHKIT_GOOGLE_CLIENT_SECRET` in `.env`, and register the
`AUTHKIT_GOOGLE_REDIRECT` URL the installer wrote (`/auth/social/google/callback` by default) in
Google's developer console. No change to the user model is required: the package reads the
`socials` table directly.

You can use it independently with its own web routes and Blade component. With
`laranail/authkit-preset`, it automatically uses the preset's route mounts and middleware, and the
preset login and registration pages render its buttons when the package is installed. The preset
does not require or install this package; install it separately when social login is wanted.

## Quick start guide and usage

### Getting started

The installer above does all of this; these are the pieces it touches, for a manual setup:

- **Publish tags** — `laranail::authkit-social-login-config` (writes `config/laranail/authkit-social-login.php`, read at
  `laranail.authkit-social-login`; a bare `config/authkit-social-login.php` is a deprecated fallback),
  `laranail::authkit-social-login-migrations` (the `socials` table) and, only if you want to own the
  route file, `laranail::authkit-social-login-routes`.
- **Migrations** — run `php artisan migrate` after publishing; the package does not load its
  migrations automatically.
- **Env keys** — per provider, `AUTHKIT_<PROVIDER>_CLIENT_ID`, `AUTHKIT_<PROVIDER>_CLIENT_SECRET` and
  `AUTHKIT_<PROVIDER>_REDIRECT` (for example `AUTHKIT_GOOGLE_CLIENT_ID`). Social login is on by
  default; `AUTHKIT_SOCIAL_ENABLED=false` switches it off, and `AUTHKIT_SOCIAL_API_ENABLED=true`
  adds the session-less API routes. Clear the configuration cache after changing them.

### Usage

Render one button per configured provider that has a client ID set:

```blade
<x-laranail-authkit-social-login::social-buttons />
```

Each button links to `/auth/social/{provider}`; the callback at `/auth/social/{provider}/callback`
signs the user in and redirects to the intended URL, falling back to `/dashboard`
(`AUTHKIT_SOCIAL_AFTER_LOGIN`). Signed-in users manage their linked providers on the
package's own page (the name below is the standalone one; with `authkit-preset` installed it takes
the preset mount's name prefix instead):

```blade
<a href="{{ route('laranail-social.user-social-accounts.index') }}">Linked accounts</a>
```

The full walkthrough is in [Getting started](docs/getting-started.md).

Everything else is in the [documentation index](#documentation).

## <a name="documentation"></a>Documentation

The documentation lives in this repository under [`docs/`](docs/).

### Guides

- [Installation](docs/installation.md) — requirements, standalone and preset-assisted install
- [Getting started](docs/getting-started.md) — configure providers and wire the login buttons
- [Social login](docs/social-login.md) — providers, callbacks and identity linking
- [Social accounts](docs/connected-accounts.md) — the linked-providers page and unlinking rules
- [Configuration](docs/configuration.md) — every key in `laranail.authkit-social-login`
- [Architecture](docs/architecture.md) — how this package extends the core, and why it is built this way
- [Release](docs/release.md) — versioning, tagging and what a release must carry

### Operations

- [Lifecycle events](docs/events.md) — public audit payloads and Laravel listener setup

- [Environment variables](docs/environment.md) — supported names and cached configuration
- [Localization](docs/localization.md) — language files, overrides and fallback
- [Troubleshooting](docs/troubleshooting.md) — setup checks and common failures
- [Feature scope](docs/feature-scope.md) — supported flows, limitations and deferred work
- [Installer](docs/installer.md) — previews and repeatable skipped steps
- [Testing](docs/testing.md) — local checks, CI databases and integration limits

## Contributing & security

See [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities privately through the
[security policy](SECURITY.md). Participation follows the
[Laranail Code of Conduct](https://github.com/laranail/.github/blob/main/CODE_OF_CONDUCT.md).

## License

MIT © Simtabi LLC. See [LICENSE](LICENSE).
