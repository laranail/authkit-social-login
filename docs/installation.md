# Installation

Add the repositories block, require the package, then configure the providers you want.

## Requirements

PHP 8.4 or 8.5, Laravel 13, and `laranail/authkit` on the same application.

## The repositories block

`laranail/*` packages are resolved through git rather than Packagist, so Composer needs to be told
where to find them. Composer ignores a dependency's own `repositories`, which is why the block goes
in the **application's** `composer.json` and lists the whole transitive closure rather than just
this package:

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

The four `vcs` entries are the full `laranail/*` closure of this package's `require` block: it
requires `authkit`, `console`, `enumerator` and `package-tools` directly, and `authkit` requires
only `package-tools`, which requires no other `laranail/*` package. The last two lines replace the
default Packagist repository with one that excludes `laranail/*`, so a stale Packagist copy under
the same name can never win over the git source. Every other dependency, Socialite included, still
resolves from Packagist.

If your application already declares a repositories block, merge these entries into it rather than
adding a second one, and keep a single `{ "packagist.org": false }`.

## Require it

```bash
composer require laranail/authkit-social-login
```

The service provider is discovered automatically.

## Publish the config

```bash
php artisan vendor:publish --tag=laranail::authkit-social-login-config
```

That writes `config/laranail/authkit-social-login.php`, which the package reads under the
`laranail.authkit-social-login` key.

> A config published before 2026-10 sits at `config/authkit-social-login.php`, under the bare
> `authkit-social-login` key. It is still read, as a deprecated fallback: each key it sets wins over
> the packaged default, and the package emits a deprecation at boot. Republish with the command above
> and delete the old file; the bare key stops being read in the next minor after 0.1.

## Configure providers

```bash
php artisan laranail::authkit-social-login.install --social=google
php artisan migrate
```

The installer publishes the package config and social migration, selects providers, and adds
provider credential and callback variables to `.env` when the files exist. The migration is
published even when no provider is selected yet, since the connected-accounts route needs the
`socials` table. Run `php artisan migrate`, then add the generated client ID and secret and register
each callback URL with its provider.

## Using the package with authkit-preset

The preset has no dependency on this package, including as a development dependency. Add this
package separately to opt into social login. Once installed, the preset detects the social button
component on login and registration pages, and this package inherits the preset route mounts,
guards, and middleware. The social package remains usable without the preset; configure its `web`
settings and render its Blade component from your own views.

---

[← Docs index](../README.md#documentation)
