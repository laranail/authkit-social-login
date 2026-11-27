# Lifecycle events and audit hooks

`laranail/authkit-social-login` dispatches the public events below through Laravel's normal event dispatcher.
Classes live under `Simtabi\Laranail\AuthKit\Social\Events`. Payloads are immutable metadata snapshots: scalar IDs,
booleans and, for OAuth consent, scope-name lists. They contain no model/request objects,
passwords, bearer/provider tokens, authorization codes, recovery values or client secrets.

## Event catalog

| Event | Public payload | Dispatch point |
| --- | --- | --- |
| `IdentityLinked` | `userType: string`, `userId: string`, `provider: string`, `socialId: string` | After a new social row is persisted, for explicit connect, verified-email matching or provisioning. Existing links do not emit it again. |
| `IdentityUnlinked` | `userType: string`, `userId: string`, `provider: string` | After an actual social row is deleted. Refused or repeated removal emits nothing. |
| `SocialAuthenticationFailed` | `provider: string`, `guard: string`, `surface: string` | A recognized provider callback is rejected. No provider error, email, code or token is included. |
| `SocialAuthenticationSucceeded` | `userType: string`, `userId: string`, `provider: string`, `guard: string`, `surface: string` | After provider identity acceptance on web, or successful token issuance on API. `surface` is `web` or `api`. |

`userType`/`actorType` are the authenticatable PHP class names; IDs are strings so UUID and integer
models share one contract. Nullable fields are marked `?`. These are public payloads; listeners
should use the named properties rather than infer a meaning from dispatch order.

## Register a listener

In the host application's service provider `boot()` method:

```php
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Simtabi\Laranail\AuthKit\Social\Events\IdentityLinked;

Event::listen(IdentityLinked::class, function (IdentityLinked $event): void {
    Log::info('authkit.IdentityLinked', get_object_vars($event));
});
```

Use your application's audit channel or listener class when it needs persistence or another sink.
Normal Laravel event discovery and class listeners work too. The package provides extension hooks,
not an audit table, retention policy or external logging service.

## Identity acceptance and explicit linking

A web `SocialAuthenticationSucceeded` means the provider identity was accepted; the caller still
owns final session creation and any additional authentication requirements. An API success follows
successful token issuance. Do not treat an identity-resolution event alone as proof that every
application authentication step has completed.

`IdentityLinked` is emitted at the single social-row creation action, so implicit verified-email
matching, provisioning and deliberate connecting share the same hook. Existing ownership and
idempotent connects do not claim a new link. Rejected connects or last-provider unlink refusals
never emit a successful mutation. Unknown provider slugs and request validation failures can be
rejected before the callback action and have no authentication event.

## Listener execution and transactions

Dispatch is synchronous after the operation described above completes. Listener exceptions
propagate; a failing listener can interrupt the response after a write has already happened. Keep
authentication-path listeners small and reliable, or queue expensive work.

Events do not start or commit a surrounding application transaction. If an application wraps an
operation in a transaction, its audit consumer must respect commit/rollback (for example, queue
listeners with the host's after-commit configuration). A synchronous event is not a durable
outbox or proof that a caller's later transaction committed. No listener can veto an operation
by returning `false`; replace the documented action/contract for pre-operation policy.

## Verification

The normal test suite covers payload contracts, successful dispatch counts and rejection paths.
For a focused check:

```bash
XDEBUG_MODE=off vendor/bin/pest --colors=never --no-coverage tests/Architecture/EventPayloadTest.php tests/Feature/LifecycleEventsTest.php
```

OAuth's authorization/device tests additionally cover approve, deny and invalid session tokens.
Social's deliberate-connect tests additionally cover idempotent and refused linking.

---

[← Docs index](../README.md#documentation)
