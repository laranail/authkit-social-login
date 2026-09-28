# Installation

Three steps: add the repositories block, require the package, switch it on.

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
    { "type": "vcs", "url": "https://github.com/laranail/captcha.git" },
    { "type": "vcs", "url": "https://github.com/laranail/db-tools.git" }
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

## Switch it on

```env
AUTHKIT_SOCIAL_ENABLED=true
```

Until this is true the package registers nothing. Installing it cannot change how an application
authenticates on its own — a property the test suite asserts.

---

[← Docs index](../README.md#documentation)
