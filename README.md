# laranail/authkit-social-login

[![Tests](https://img.shields.io/github/actions/workflow/status/laranail/authkit-social-login/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/laranail/authkit-social-login/actions/workflows/tests.yml)
[![License MIT](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)

`laranail/authkit-social-login` is not published to Packagist, so there is no registry-version badge to show: see [Install](#install).

> Social login for the authkit family: Socialite-backed providers, identity linking and account provisioning.

Requires PHP 8.4+ and Laravel 13, and extends [`laranail/authkit`](https://github.com/laranail/authkit).

Supports Google, Apple, X, LinkedIn, PayPal, GitHub, and Facebook. GitHub uses verified primary
email addresses; Facebook requires explicit linking before login. See
[provider setup](docs/social-login.md#github-and-facebook-setup) for credentials and callback URLs.

## Install

`laranail/*` packages resolve through git, not Packagist. Add the repositories block to your
application's `composer.json`:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/laranail/authkit.git" },
    { "type": "vcs", "url": "https://github.com/laranail/console.git" },
    { "type": "vcs", "url": "https://github.com/laranail/enumerator.git" },
    { "type": "vcs", "url": "https://github.com/laranail/package-tools.git" },
    { "type": "composer", "url": "https://repo.packagist.org", "exclude": ["laranail/*"] },
    { "packagist.org": false }
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

The installer above does all of this **except declaring the feature**, which lives in the core's
config and so belongs to the application; it warns when it finishes if the feature is still off.
These are the pieces it touches, for a manual setup:

- **Publish tags** — `laranail::authkit-social-login-config` (writes `config/laranail/authkit-social-login.php`, read at
  `laranail.authkit-social-login`; a bare `config/authkit-social-login.php` is a deprecated fallback),
  `laranail::authkit-social-login-migrations` (the `socials` table) and, only if you want to own the
  route file, `laranail::authkit-social-login-routes`.
- **Migrations** — run `php artisan migrate` after publishing; the package does not load its
  migrations automatically.
- **Declare the feature** — add `Simtabi\Laranail\AuthKit\Support\AuthKit::SOCIAL_LOGIN` to
  `laranail.authkit.fortify.features`. The browser buttons and routes are gated on it; without it
  nothing renders and nothing mounts, whatever else is configured. Upgrading from a release that
  used `web.enabled`? See [the upgrade guide](docs/upgrading.md).
- **Env keys** — per provider, `AUTHKIT_<PROVIDER>_CLIENT_ID`, `AUTHKIT_<PROVIDER>_CLIENT_SECRET` and
  `AUTHKIT_<PROVIDER>_REDIRECT` (for example `AUTHKIT_GOOGLE_CLIENT_ID`). The package's master
  switch is on by default and `AUTHKIT_SOCIAL_ENABLED=false` turns the whole package off, API
  included — it is a different scope from the feature above, which gates the browser surface only,
  so both have to say yes. `AUTHKIT_SOCIAL_API_ENABLED=true` adds the session-less API routes.
  Clear the configuration cache after changing them.

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

Full documentation: <https://opensource.simtabi.com/documentation/laranail/authkit-social-login/>

### Guides

- [Installation](docs/installation.md) — requirements, standalone and preset-assisted install
- [Getting started](docs/getting-started.md) — configure providers and wire the login buttons
- [Social login](docs/social-login.md) — providers, callbacks and identity linking
- [Social accounts](docs/connected-accounts.md) — the linked-providers page and unlinking rules
- [Configuration](docs/configuration.md) — every key in `laranail.authkit-social-login`
- [Architecture](docs/architecture.md) — how this package extends the core, and why it is built this way
- [Release](docs/release.md) — versioning, tagging and what a release must carry
- [Upgrading](docs/upgrading.md) — moving off the removed `web.enabled` key to the `social-login` feature

## Community

Questions and ideas: [GitHub issues](https://github.com/laranail/authkit-social-login/issues).

## Contributing & security

See [CONTRIBUTING.md](CONTRIBUTING.md). Report security issues privately — [SECURITY.md](SECURITY.md).

## License

MIT. See [LICENSE](LICENSE).

## Localization

English package messages ship in `lang/en/messages.php` under `laranail/authkit-social-login::messages`.
Laravel uses the current application locale and its configured fallback locale.
Publish the language files to customize messages or add locales:

```sh
php artisan vendor:publish --tag=laranail::authkit-social-login-translations
```

Edit `lang/vendor/laranail/authkit-social-login/en/messages.php`, or add another locale directory there.
You can override individual keys; the remaining messages keep their package defaults.

## Installer previews and skipped steps

Preview the actions selected by your installation options without writing files:

```sh
php artisan laranail::authkit-social-login.install --social=google --publish-routes --dry-run --no-interaction
```

Repeat `--skip` to leave selected steps untouched. Other requested steps still run:

```sh
php artisan laranail::authkit-social-login.install --social=google --publish-routes --skip=config --skip=environment --no-interaction
```

| Step | Controls |
| --- | --- |
| `config` | Config publishing and selected provider updates |
| `migrations` | Social accounts migration publishing |
| `environment` | .env and .env.example provider credentials/callback URLs |
| `routes` | Optional web route publishing |

Unknown step names fail before any changes. Optional steps still require their enabling options;
`--skip` does not enable a step. Dry-run can be combined with `--skip` and `--force` and performs
no writes. On a normal run, `--force` retains its existing overwrite behavior and never overrides
a skipped step. When skipping prerequisites such as config, migrations, or model edits, provide
those changes in your application before using the selected features.
