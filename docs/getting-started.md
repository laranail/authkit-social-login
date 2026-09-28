# Getting started

The smallest setup that does something, and what to expect while the package is a skeleton.

## Today

The package installs, registers its config under `laranail.authkit-social-login`, and stops there. That is the
whole behaviour, and it is deliberate — see [architecture](architecture.md) for why the
implementation waits on the core.

```php
config('laranail.authkit-social-login.enabled');   // false until you set AUTHKIT_SOCIAL_ENABLED=true
```

## What it will look like

Social login will be driven entirely by configuration and the core's seams. You will not wire
controllers or routes by hand:

- Socialite 2.0 service provider — metadata exchange, assertion consumer, signature validation
- OpenID Connect relying party — discovery, authorization code flow with PKCE
- Tenant-to-IdP mapping, so one application can face many identity providers
- Just-in-time provisioning through the core’s identity-resolution path

## Verifying the install

```bash
php artisan vendor:publish --tag=laranail::authkit-social-login-config
php artisan about
```

`vendor:publish` succeeding with the namespaced tag confirms the provider is registered. If the tag
is not offered, the package is not installed or discovery is disabled.

---

[← Docs index](../README.md#documentation)
