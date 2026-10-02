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

That writes `config/laranail/authkit-social-login.php`. The nested directory is deliberate: Laravel turns a
nested config directory into a nested key, so the file is read as `laranail.authkit-social-login`, which is the
key the package merges its defaults into.

## Configure providers

```bash
php artisan laranail::authkit-social-login.install --social=google
php artisan migrate
```

The installer publishes the package config, selects providers, publishes the social migration, and
adds provider credential and callback variables to `.env` when the files exist. Add the generated
client ID and secret, then register each callback URL with its provider.

## Using the package with authkit-preset

The preset has no dependency on this package, including as a development dependency. Add this
package separately to opt into social login. Once installed, the preset detects the social button
component on login and registration pages, and this package inherits the preset route mounts,
guards, and middleware. The social package remains usable without the preset; configure its `web`
settings and render its Blade component from your own views.

---

[← Docs index](../README.md#documentation)
