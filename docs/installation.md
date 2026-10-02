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
    { "type": "vcs", "url": "https://github.com/laranail/package-tools.git" }
]
```

## Require it

```bash
composer require laranail/authkit-social-login
```

The service provider is discovered automatically.

## Publish the config

```bash
php artisan vendor:publish --tag=laranail::authkit-social-login-config
```

That writes `config/authkit-social-login.php`, which Laravel loads under the `authkit-social-login`
key.

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
