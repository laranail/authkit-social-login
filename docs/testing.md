# Testing

Install development dependencies from this checkout, then run the same sequential command used
by CI:

```bash
composer install
XDEBUG_MODE=off vendor/bin/pest --colors=never --no-coverage
composer pint
```

`composer pint-fix` formats PHP before a push. `composer test` currently requests parallel mode;
the explicit command above is the CI reproduction command. Default local tests use SQLite.
GitHub Actions runs PHP 8.4/8.5 dependency combinations and PHP 8.5 jobs against MySQL, MariaDB and
PostgreSQL with coverage disabled. Server database jobs run sequentially; no local database
installation is required.

Provider tests mock remote HTTP responses, including real Socialite driver parsing. They cover
verified-email boundaries, linked identities, explicit linking, rejection, configuration and
installer behavior. They do not contact live providers. Verify credentials, provider approval,
callbacks and session/cookie handling with a provider test app on your deployed origin.

## Package guard coverage

The normal sequential command collects `tests/Architecture` as well as feature tests:

- `AssertsNoFacadeCalls` scans all package classes with a file-count floor. Providers are the
  composition root. Retained static/framework bridges have a reason beside the exact call;
  there is no whole-file allowlist. New hidden service dependencies fail the scan.
- `AssertsDriverContract` rejects bare package config reads in source and routes. Driver tests
  resolve the actual configured guards, issuers, providers or frontend stacks; these checks do
  not replace a manager with a mock.
- Published-config tests use application files and the package's actual load path, not a config
  value planted by the harness. Core, preset and OAuth use Laravel's config bootstrap; social
  exercises the package-tools published override bridge.
- `AssertsNoStateLeak` runs two distinct request identities/mounts in one container, retaining
  singletons and statics while flushing scoped instances. The first marker must be observed and
  cannot leak into the second request.
- The credential guard still scans the full test tree, with runtime-generated secrets.

For a focused run:

```bash
XDEBUG_MODE=off vendor/bin/pest --colors=never --no-coverage tests/Architecture tests/Feature/DriverContractTest.php tests/Feature/RequestStateIsolationTest.php tests/Feature/PublishedConfigOverrideTest.php
```

Resolve actions, controllers and rules through Laravel's container so their constructor contracts
are supplied. Manual construction and subclasses with their own constructors must supply or
forward those dependencies. Public execution methods and static compatibility APIs keep their
existing signatures.

---

[← Docs index](../README.md#documentation)
