# laranail/authkit-social-login

[![Tests](https://img.shields.io/github/actions/workflow/status/laranail/authkit-social-login/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/laranail/authkit-social-login/actions/workflows/tests.yml)
[![License MIT](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)

`laranail/authkit-social-login` is not published to Packagist, so there is no registry-version badge to show: see [Install](#install).

> Social login for the authkit family: Socialite-backed providers, identity linking and account provisioning.

Requires PHP 8.4+ and Laravel 13, and extends [`laranail/authkit`](https://github.com/laranail/authkit).

## Install

`laranail/*` packages resolve through git, not Packagist. Add the repositories block to your
application's `composer.json`:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/laranail/authkit.git" },
    { "type": "vcs", "url": "https://github.com/laranail/console.git" },
    { "type": "vcs", "url": "https://github.com/laranail/enumerator.git" },
    { "type": "vcs", "url": "https://github.com/laranail/package-tools.git" },
    { "type": "vcs", "url": "https://github.com/laranail/captcha.git" },
    { "type": "vcs", "url": "https://github.com/laranail/db-tools.git" }
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

Nothing beyond the Install steps above: the installer publishes the config and the `socials`
migration, and the `AUTHKIT_GOOGLE_*` values in `.env` plus the callback URL registered with Google
complete the setup. Clear the configuration cache after changing those values.

### Usage

```blade
{{-- resources/views/auth/login.blade.php: renders a "Continue with Google" link to /auth/social/google --}}
<x-laranail-authkit-social-login::social-buttons />
{{-- The package's own callback at /auth/social/google/callback signs the user in and redirects to the intended URL, else /dashboard. --}}
```

The full walkthrough is in [Getting started](docs/getting-started.md); everything else is in the [documentation index](#documentation).

## <a name="documentation"></a>Documentation

Full documentation: <https://opensource.simtabi.com/documentation/laranail/authkit-social-login/>

### Guides

- [Installation](docs/installation.md) — requirements, standalone and preset-assisted install
- [Getting started](docs/getting-started.md) — configure providers and wire the login buttons
- [Social login](docs/social-login.md) — providers, callbacks and identity linking
- [Social accounts](docs/connected-accounts.md) — the linked-providers page and unlinking rules
- [Configuration](docs/configuration.md) — every key in `authkit-social-login`
- [Architecture](docs/architecture.md) — how this package extends the core, and why it is built this way
- [Release](docs/release.md) — versioning, tagging and what a release must carry

## Community

Questions and ideas: [GitHub issues](https://github.com/laranail/authkit-social-login/issues).

## Contributing & security

See [CONTRIBUTING.md](CONTRIBUTING.md). Report security issues privately — [SECURITY.md](SECURITY.md).

## License

MIT. See [LICENSE](LICENSE).
