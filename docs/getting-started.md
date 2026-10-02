# Getting started

Install the package, configure at least one provider, and run its migration:

```bash
composer require laranail/authkit-social-login
php artisan laranail::authkit-social-login.install --social=google
php artisan migrate
```

Add the generated client ID and secret to `.env`, then register this callback URL in the provider's
developer console:

```text
https://your-app.test/auth/social/google/callback
```

The package registers web routes and provides a reusable Blade button component. Use it on your
own login and registration views:

```blade
<x-laranail-authkit-social-login::social-buttons />
```

## With authkit-preset

The preset does not install the social package. For the recommended integration, add
`laranail/authkit-social-login` explicitly to your application. The preset login and registration
views detect the package component and render it when available. Social routes automatically use
the preset's configured prefixes, guards, middleware, and route names.

The social package also works without the preset. In that case, configure its `web` route settings
and include its Blade component in your own views.

See [installation](installation.md), [configuration](configuration.md), and
[social login](social-login.md) for provider details and customization.

---

[← Docs index](../README.md#documentation)
