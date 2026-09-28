# Configuration

Everything the package reads lives under `laranail.authkit-social-login`.

## Where the config lives

| | |
|---|---|
| Package default | `config/laranail/authkit-social-login.php` inside the package |
| Published to | `config/laranail/authkit-social-login.php` in the application |
| Config key | `laranail.authkit-social-login` |
| Publish tag | `laranail::authkit-social-login-config` |

The key is namespaced because Laravel's config is a single flat map underneath. A package that
claimed a bare `authkit-social-login` key would sit one collision away from any other package or the
application's own config, and the failure would be silent.

## Keys

| Key | Type | Default | What it does |
|---|---|---|---|
| `enabled` | bool | `false` | Master switch. While false the provider registers nothing. |

## Environment

| Variable | Maps to |
|---|---|
| `AUTHKIT_SOCIAL_ENABLED` | `laranail.authkit-social-login.enabled` |

## Overriding

Publishing gives you the file; edit it there. `mergeConfigFrom` merges the package defaults
*underneath* your published values, so a key you omit falls back to the package default and a key
you set wins. The merge is shallow, so replace a nested array wholesale rather than partially.

---

[← Docs index](../README.md#documentation)
