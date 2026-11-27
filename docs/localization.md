# Localization

English messages ship with `laranail/authkit-social-login`. Laravel uses the application locale and fallback
locale; publishing is optional unless you want to change messages or add another language.

## Publish the language files

```bash
php artisan vendor:publish --tag=laranail::authkit-social-login-translations
```

Edit `lang/vendor/laranail/authkit-social-login/en/messages.php`. To add a locale, create a matching file such
as `lang/vendor/laranail/authkit-social-login/fr/messages.php`, preserving the keys and placeholders.
Partial overrides fall back to the shipped messages.

```php
__('laranail/authkit-social-login::messages.authentication_failed');
```

Keep placeholder names intact and escape translated output for its HTML or JavaScript context.
See [Configuration](configuration.md) for the package config namespace; it differs from the
translation namespace.

---

[← Docs index](../README.md#documentation)
