# franbarbalopez/mirror

Mirror is a Laravel package for secure user impersonation. It lets an authorized user temporarily authenticate as another user to debug issues, provide support, or verify user experience.

The package stores a signed impersonation payload in the session, verifies that payload with HMAC-SHA256, supports session-backed multi-guard applications, exposes Blade condition directives, and dispatches lifecycle events for audit logging.

## Key Concepts

- **Impersonator**: The authenticated user starting the impersonation session.
- **Impersonated**: The target `Authenticatable` model being logged in as.
- **Impersonatable**: Contract implemented by models that participate in impersonation authorization.
- **Context**: Custom array passed to `Mirror::impersonate()` and signed with the session payload. Use it for audit metadata such as ticket ids or reasons.
- **Target guard**: The session guard used to authenticate the impersonated model.
- **Impersonator guard**: The authenticated session guard that will be restored by `Mirror::leave()`.
- **TTL**: Configured maximum impersonation age in seconds. Mirror reports expiration through `Mirror::expired()`; the application decides the response.
- **Signed session payload**: Session data containing impersonator id, impersonator guard, impersonated id, impersonated guard, started timestamp, and context.

## Installation

```bash
composer require franbarbalopez/mirror
```

Publish the config when the application needs to customize TTL or session storage:

```bash
php artisan vendor:publish --tag=mirror
```

## Configuration

Published config file: `config/mirror.php`.

| Key | Default | Description |
|-----|---------|-------------|
| `ttl` | `1800` | Maximum impersonation age in seconds. Set to `null` to disable expiration. |
| `session.key` | `mirror.impersonation` | Session namespace for the impersonation payload and signature. Can be set with `MIRROR_SESSION_KEY`. |

Avoid TTL values above 60 minutes for normal support/admin workflows.

## Impersonatable Contract

Implement `Mirror\Contracts\Impersonatable` on authenticatable models that can impersonate or be impersonated.

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Mirror\Contracts\Impersonatable;

class User extends Authenticatable implements Impersonatable
{
    public function canImpersonate(): bool
    {
        return $this->hasRole('admin');
    }

    public function canBeImpersonated(): bool
    {
        return ! $this->hasRole('super-admin');
    }
}
```

`canImpersonate()` is checked on the impersonator. `canBeImpersonated()` is checked on the target model.

## Facade API

Use `Mirror\Facades\Mirror` from application controllers, services, middleware, or tests.

```php
use Mirror\Facades\Mirror;

Mirror::impersonate($user);
Mirror::impersonate($user, guard: 'web');
Mirror::impersonate($user, context: ['ticket_id' => 123]);

$context = Mirror::leave();
```

### `impersonate()`

```php
Mirror::impersonate(Authenticatable $target, ?string $guard = null, array $context = []): void
```

Starts impersonation for the target model. The optional `guard` is the target guard. The optional `context` array is signed into the impersonation payload.

### `leave()`

```php
Mirror::leave(): array
```

Stops the active impersonation, logs out the impersonated user, restores the original impersonator with the remembered-login state when applicable, clears Mirror's session payload, dispatches `ImpersonationStopped`, and returns the signed context array.

### State helpers

```php
Mirror::active();       // bool
Mirror::expired();      // bool
Mirror::impersonator(); // ?Authenticatable
Mirror::impersonated(); // ?Authenticatable
Mirror::context();      // array
```

Use these helpers in controllers, middleware, layouts, and audit logic. `Mirror::expired()` returns `false` when no payload exists or when `mirror.ttl` is `null`.

## Routes And Controllers

Use POST routes for start and leave actions because both mutate authentication state.

```php
use App\Http\Controllers\UserImpersonationController;
use Illuminate\Support\Facades\Route;

Route::post('/admin/users/{user}/impersonate', [UserImpersonationController::class, 'start'])
    ->middleware('auth')
    ->name('impersonation.start');

Route::post('/impersonation/leave', [UserImpersonationController::class, 'leave'])
    ->middleware('auth')
    ->name('impersonation.leave');
```

```php
use App\Models\User;
use Mirror\Facades\Mirror;

class UserImpersonationController
{
    public function start(User $user)
    {
        Mirror::impersonate($user, context: [
            'reason' => 'support',
        ]);

        return redirect()->route('dashboard');
    }

    public function leave()
    {
        Mirror::leave();

        return redirect()->route('admin.users.index');
    }
}
```

Route names are application-defined. Keep route authorization explicit with middleware, policies, or gates in addition to model capability checks.

## Blade Directives

Mirror registers Blade condition directives for impersonation-aware UI.

| Directive | Description |
|-----------|-------------|
| `@impersonating` | Renders when a valid impersonation payload is active. Accepts an optional guard name. |
| `@notImpersonating` | Renders when no valid impersonation payload is active. Accepts an optional guard name. |
| `@canImpersonate` | Renders when the current user implements `Impersonatable` and `canImpersonate()` returns true. Accepts an optional guard name. |
| `@canBeImpersonated($user)` | Renders when the target model implements `Impersonatable` and `canBeImpersonated()` returns true. Accepts an optional guard name as the second argument. |

```blade
@impersonating
    <div class="alert alert-warning">
        You are impersonating {{ auth()->user()->name }}.

        <form method="POST" action="{{ route('impersonation.leave') }}">
            @csrf
            <button type="submit">Exit impersonation</button>
        </form>
    </div>
@endimpersonating
```

```blade
@canImpersonate
    @canBeImpersonated($user)
        <form method="POST" action="{{ route('impersonation.start', $user) }}">
            @csrf
            <button type="submit">Impersonate</button>
        </form>
    @endcanBeImpersonated
@endcanImpersonate
```

Guard-specific check:

```blade
@impersonating('web')
    Impersonating through the web guard.
@endimpersonating
```

## Guard Resolution

Mirror only supports guards backed by Laravel's `session` driver.

The impersonator guard is resolved from the authenticated session guard. The target guard is resolved in this order:

1. Explicit `guard` argument passed to `Mirror::impersonate()`.
2. Target model `guardName()` method.
3. Target model `guard_name` attribute or default property.
4. Auth provider inference from `auth.guards` and `auth.providers`.

```php
Mirror::impersonate($customer, guard: 'customer');
```

When a model or provider inference returns multiple matching guards, Mirror uses the first matching session guard.

## Events

Mirror dispatches these events:

| Event | When |
|-------|------|
| `Mirror\Events\ImpersonationStarted` | After the signed payload is stored and the target user is logged in. |
| `Mirror\Events\ImpersonationStopped` | After the original impersonator is restored and the signed payload is cleared. |

Both events expose `impersonator`, `impersonated`, and `context`.

```php
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mirror\Events\ImpersonationStarted;

Event::listen(ImpersonationStarted::class, function (ImpersonationStarted $event): void {
    Log::info('User impersonation started', [
        'impersonator_id' => $event->impersonator->getAuthIdentifier(),
        'impersonated_id' => $event->impersonated->getAuthIdentifier(),
        'context' => $event->context,
    ]);
});
```

## Session Integrity

Mirror stores two session values under the configured `mirror.session.key` namespace:

- `<key>.payload`
- `<key>.signature`

The signature is generated with HMAC-SHA256 using `config('app.key')`. When a payload is missing its signature or the signature does not match, Mirror clears the impersonation session state and throws an exception.

## Exceptions

Common exceptions exposed by the package:

| Exception | Typical cause |
|-----------|---------------|
| `CanNotImpersonate` | The impersonator does not implement/allow `canImpersonate()`. |
| `CanNotBeImpersonated` | The target does not implement/allow `canBeImpersonated()`. |
| `ImpersonationAlreadyActive` | An impersonation is already active. Nested impersonation is rejected. |
| `ImpersonationNotActive` | `Mirror::leave()` was called without an active impersonation. |
| `MissingAuthenticatedSessionGuard` | No authenticated guard using the `session` driver was found. |
| `GuardDoesNotUseSessionDriver` | The provided or inferred guard is not backed by `SessionGuard`. |
| `CannotInferTargetGuard` | Mirror could not infer a session guard for the target model. |
| `MissingImpersonationSignature` | Session payload exists but its signature is missing. |
| `InvalidImpersonationSignature` | Session payload was tampered with or no longer matches its signature. |

## Common Patterns

### Add audit context

```php
Mirror::impersonate($user, context: [
    'ticket_id' => $ticket->id,
    'reason' => 'support',
]);
```

Read the context while active with `Mirror::context()` or after leaving from `Mirror::leave()`'s return value.

### Handle expiration in app code

```php
if (Mirror::active() && Mirror::expired()) {
    Mirror::leave();

    return redirect()->route('login')
        ->with('status', 'Your impersonation session expired.');
}
```

### Add a persistent banner

Put an `@impersonating` banner in authenticated layouts so support users always know when they are acting as another account.

## Security Practices

- Use POST routes with CSRF protection for start and leave actions.
- Protect impersonation routes with application auth middleware, policies, or gates.
- Implement both `canImpersonate()` and `canBeImpersonated()` with conservative rules.
- Keep TTL short for admin/support workflows and handle `Mirror::expired()` deliberately.
- Use events to record who impersonated whom and why.
- Use small context payloads; avoid sensitive secrets in context because it is stored in the session.
- Do not manually mutate auth sessions or switch guards when Mirror can manage the lifecycle.
