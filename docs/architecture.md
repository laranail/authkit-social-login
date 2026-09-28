# Architecture

How this package relates to the core, why it is a separate package, and why it is empty.

## Where it sits

```
laranail/authkit            Simtabi\Laranail\AuthKit\           headless core + REST API
laranail/authkit-preset     Simtabi\Laranail\AuthKit\Preset\    Blade scaffolding
laranail/authkit-social-login             Simtabi\Laranail\AuthKit\Social\         this package
```

The family shares one root namespace and each sibling is a segment under it. Two packages mapping
nested PSR-4 prefixes is fine: Composer's loader matches the longest prefix first, so
`Simtabi\Laranail\AuthKit\Social\` resolves here and everything shallower resolves into the core.

## Why a separate package

Social login drags dependencies — a maintained Socialite toolkit and an OAuth client, rather than speaking either protocol directly — that most consumers of the core would pay for and
never use. Keeping it a sibling means the core stays small and this cost is opt-in.

The price of that choice is that the seams have to be real. A sub-package that cannot do its job
without editing the core is not extending the core, it is forking it.

## Why it is empty

This package binds to core seams that do not exist yet, chiefly **`ResolveIdentityInterface`**. Those seams
change a published contract, so they land in `laranail/authkit` first — building here against a
contract that is still moving would mean rewriting this package when it settles.

Until then the package ships its skeleton: the public names, the config key, CI, and the tests that
guard them. That is not busywork — claiming the names early is what stops a later collision, and
the naming guard means the conventions cannot quietly rot before there is code to protect.

## Rules this package holds itself to

- **Never edit the core.** Everything goes through a published contract.
- **Every public name is vendor-scoped.** Config key, publish tags, and any view, translation,
  command or middleware name it later adds.
- **On by default, and this one differs from its siblings.** The other authkit siblings are inert
  placeholders that must not change behaviour on install. This package carries social login moved
  out of the core, so an upgrading application already has it configured — defaulting off would
  disable it during a routine `composer update` with nothing reported. The switch is
  `laranail.authkit-social-login.enabled`; a test asserts it defaults to **true**, and a second asserts it
  can still be turned off.
- **Compose, do not reimplement.** Laravel Socialite and `socialiteproviders/manager` do the protocol
  work; this package owns identity linking, provisioning and the provider registry.

## Scope

- Socialite-backed providers — Google, Apple, X, LinkedIn, and a custom PayPal driver
- The redirect and callback halves of the OAuth consumer flow, as overridable abstract controllers
- Identity linking and just-in-time provisioning, including the `email_verified` matrix that decides
  when a social identity may attach to an existing account
- The `socials` table and its model — a polymorphic store of provider tokens and profile data

Out of scope, and deliberately so: **`laranail/authkit-oauth`** owns OAuth *authentication* plus the
backend side — app registration, permissions, integrations. This package is the consumer half only.

---

[← Docs index](../README.md#documentation)
