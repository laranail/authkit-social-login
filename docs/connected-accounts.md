# Social accounts

The package registers a page listing the signed-in user's linked providers at
`GET /auth/user/social-accounts`; `DELETE /auth/user/social-accounts/{provider}` removes a link.
The page shows configured providers and whether each one is connected, as well as linked account
details and disconnect controls. When used with `authkit-preset`, the routes inherit its configured
guard, prefix, and middleware, and the preset dashboard links to the page when this package is
installed.

## The last sign-in method

The last remaining social link cannot be removed by default. Socially provisioned accounts receive
a random password hash the user has never chosen, so the presence of a password column does not
prove that password sign-in is available. Users can add another sign-in method or reset their
password before removing their only provider.

If the application separately records that a password was explicitly chosen, it can allow removal
of the last link:

```php
// config/authkit-social-login.php
'unlink' => ['trust_password_column' => true],
```

The account page disables removal when it would eliminate the user's final sign-in method.
Provider links remain listable and removable even if an optional provider integration is later
uninstalled.

---

[← Social login](social-login.md)
