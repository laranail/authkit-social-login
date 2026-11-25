# Upgrading

## `web.enabled` is replaced by the `social-login` feature

**Breaking.** `laranail.authkit-social-login.web.enabled` and its `AUTHKIT_SOCIAL_WEB_ENABLED`
environment variable are **removed**. The browser surface — provider buttons, sign-in routes and
connected-account management — is now switched on by declaring a feature on AuthKit's own list.

### What to change

Add the feature to `config/laranail/authkit.php`:

```php
'fortify' => [
    'features' => [
        // ...everything already there
        \Simtabi\Laranail\AuthKit\Support\AuthKit::SOCIAL_LOGIN,
    ],
],
```

Then delete `AUTHKIT_SOCIAL_WEB_ENABLED` from `.env` and `.env.example`, and remove the
`web.enabled` key from a published `config/laranail/authkit-social-login.php`. A stale key is
inert rather than harmful — nothing reads it — but it will mislead the next person.

Clear the configuration cache afterwards: `php artisan config:clear`.

### What does not change

- `enabled` still turns the whole package off, API included. It is a wider scope than this feature.
- `api.enabled` still gates the session-less API routes on its own. The feature is the browser
  surface only.
- Every other key under `web` — `guard`, `prefix`, `route_name_prefix`, `middleware`,
  `after_login`, `failed_redirect`, `routes_mode` — is unchanged. They describe *how* the surface
  mounts once it is on.

### Why

`web.enabled` was a second switch that could disagree with the first. An application could have the
package enabled, providers configured and credentials set, and still see nothing, because a flag in
a different file said no — and the failure was silent, since the buttons component renders an empty
list as no markup. The feature list is where an application already declares what it wants from
AuthKit, so social login is declared there too, once.

The identifier lives in `laranail/authkit` rather than in this package because the packages that
must agree on the name cannot see each other: the preset only *suggests* this package, and this
package cannot reach into the preset's enum. `AuthKit::hasFeature()` was written for exactly that
problem and nothing had used it for social login.

**This is an opt-in change in the unsafe direction**, so read it as such: after upgrading, a
deployment that does not declare the feature loses its social sign-in buttons. That is deliberate —
the alternative was defaulting it on, which would have reintroduced the second switch this change
exists to remove.

---

[← Docs index](../README.md#documentation)
