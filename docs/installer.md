# Installer

## Options

Repeat `--social` to select providers: `google`, `apple`, `x`, `linkedin`, `paypal`, `github`,
`facebook`. `--publish-routes` publishes browser routes; then configure route ownership as described
in [Social login](social-login.md). Run migrations separately and supply real provider credentials.
Use `--no-interaction` in provisioning. Selection does not register an application at the provider.

Preview the actions selected by your installation options without writing files:

```sh
php artisan laranail::authkit-social-login.install --social=google --publish-routes --dry-run --no-interaction
```

Repeat `--skip` to leave selected steps untouched. Other requested steps still run:

```sh
php artisan laranail::authkit-social-login.install --social=google --publish-routes --skip=config --skip=environment --no-interaction
```

| Step | Controls |
| --- | --- |
| `config` | Config publishing and selected provider updates |
| `migrations` | Social accounts migration publishing |
| `environment` | .env and .env.example provider credentials/callback URLs |
| `routes` | Optional web route publishing |

Unknown step names fail before any changes. Optional steps still require their enabling options;
`--skip` does not enable a step. Dry-run can be combined with `--skip` and `--force` and performs
no writes. On a normal run, `--force` retains its existing overwrite behavior and never overrides
a skipped step. When skipping prerequisites such as config, migrations, or model edits, provide
those changes in your application before using the selected features.

---

[← Docs index](../README.md#documentation)
