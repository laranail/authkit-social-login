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

Then:

```bash
composer require laranail/authkit-social-login
```

The package is inert until you switch it on, so installing it cannot change how your application
authenticates:

```env
AUTHKIT_SOCIAL_ENABLED=true
```

## <a name="documentation"></a>Documentation

Full documentation: <https://opensource.simtabi.com/documentation/laranail/authkit-social-login/>

### Guides

- [Installation](docs/installation.md) — requirements, the repositories block, publishing config
- [Getting started](docs/getting-started.md) — the smallest working setup
- [Configuration](docs/configuration.md) — every key in `laranail.authkit-social-login`
- [Architecture](docs/architecture.md) — how this package extends the core, and why it is built this way
- [Release](docs/release.md) — versioning, tagging and what a release must carry

## Status

Skeleton. The public names, config key and CI are in place and guarded by tests; the behaviour is
not implemented yet, and the package does nothing while `laranail.authkit-social-login.enabled` is false.

It waits on extension seams in `laranail/authkit` — chiefly `ResolveIdentityInterface`. Those change a published
contract, so they land in the core before anything is built here. See
[docs/architecture.md](docs/architecture.md).

## Community

Questions and ideas: [GitHub issues](https://github.com/laranail/authkit-social-login/issues).

## Contributing and security

See [CONTRIBUTING.md](CONTRIBUTING.md). Report security issues privately — [SECURITY.md](SECURITY.md).

## License

MIT. See [LICENSE](LICENSE).
