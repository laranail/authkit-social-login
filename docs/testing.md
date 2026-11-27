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

---

[← Docs index](../README.md#documentation)
