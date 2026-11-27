# Contributing

Where this file is silent, the [laranail contributing guide](https://github.com/laranail/.github/blob/HEAD/CONTRIBUTING.md) applies.

Thanks for helping improve `laranail/authkit-social-login`.

## Getting set up

`laranail/*` packages resolve through git rather than Packagist, so your checkout needs the
repositories block already present in `composer.json`. Then:

```bash
composer install
XDEBUG_MODE=off vendor/bin/pest --colors=never --no-coverage
composer pint
```

## What a change needs

- **Tests.** Every behavioural change carries one. Bug fixes carry a test that fails before the fix.
- **Live-registry assertions for public names.** Anything this package registers into a Laravel
  registry — config key, publish tag, view or translation namespace, command name, middleware alias
  — is asserted against the booted application, not by grepping the provider. See
  `tests/Feature/NamingConventionTest.php`.
- **Style.** `composer format` runs Pint. CI checks it.
- **A CHANGELOG entry** under `## [Unreleased]`.

## Naming rules that are not negotiable

Laravel keeps these in flat, global maps. A second package claiming the same key does not collide
loudly — it silently replaces the first, and the damage surfaces far away as a missing view or the
wrong middleware. So every public name carries the vendor and the slug:

| Surface | Shape |
|---|---|
| Config key | `laranail.authkit-social-login` |
| Config file | `config/laranail/authkit-social-login.php` |
| Publish tag | `laranail::authkit-social-login-<suffix>` |
| View namespace | `laranail/authkit-social-login::<view>` |
| Translation namespace | `laranail/authkit-social-login::<key>` |
| Blade component prefix | `laranail-authkit-social-login::<component>` |
| Artisan command | `laranail::authkit-social-login.<command>` |
| Middleware alias | `laranail-authkit-social-login` |

No bare short aliases. A `authkit-social-login:install` alias hands back exactly the collision the namespaced
name exists to prevent.

## Extending the core

This package extends `laranail/authkit` through its published seams and never edits it. If you find
yourself needing to change the core to make something work here, that is a signal the seam is
missing — raise it against the core rather than working around it.

## Security

Do not open a public issue for a security problem. See [SECURITY.md](SECURITY.md).

## Documentation and pull requests

Branch from `main` and use Conventional Commits. Keep the README to installation, a working quick
start and the documentation index; put detailed guides in `docs/`. Link new pages from the index,
include a return link, and verify commands, route names, config keys and supported features against
the current implementation. Link sibling-package docs by GitHub URL so they work outside a monorepo.

Before opening a PR, run the sequential test command above and `composer pint`. Database jobs in
GitHub Actions cover MySQL, MariaDB and PostgreSQL; SQLite is the default local test database.
No local server database installation is needed. `composer test` currently selects parallel mode;
use the explicit command above to reproduce the sequential CI run.

Keep PR descriptions concise, link the issue, list validation, and update `CHANGELOG.md` under
`[Unreleased]`. For authentication behavior, include rejection and authorization cases. Never put
real tokens, passwords or provider secrets in fixtures, logs or reports.

Participation follows the [Laranail Code of Conduct](https://github.com/laranail/.github/blob/main/CODE_OF_CONDUCT.md).
