---
name: mirror-development
description: "Implement secure user impersonation in Laravel applications using franbarbalopez/mirror. Activates when working with Mirror, Mirror::impersonate, Mirror::leave, Impersonatable, impersonation Blade directives, support login-as-user flows, admin user switching, impersonation guards, TTL expiration, or impersonation audit events."
license: MIT
metadata:
  author: franbarbalopez
---

# Mirror Development

Mirror is a Laravel package for secure user impersonation. Use it when an authorized user, usually an administrator or support agent, needs to temporarily authenticate as another user to debug issues, provide support, or verify user experience.

## When to Apply

Activate this skill when:

- Adding login-as-user or impersonation flows to a Laravel application
- Calling `Mirror::impersonate()`, `Mirror::leave()`, or Mirror state helpers
- Implementing `Mirror\Contracts\Impersonatable` on authenticatable models
- Adding impersonation buttons, banners, or Blade condition directives
- Handling impersonation expiration, guard resolution, context, or audit logging
- Debugging Mirror exceptions around guards, permissions, signatures, or active sessions

## Basic Usage

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

Use POST routes for both actions because they mutate authentication state:

```php
Route::post('/admin/users/{user}/impersonate', [UserImpersonationController::class, 'start'])
    ->middleware('auth')
    ->name('impersonation.start');

Route::post('/impersonation/leave', [UserImpersonationController::class, 'leave'])
    ->middleware('auth')
    ->name('impersonation.leave');
```

Route names are application-defined. Follow the host application's route naming and authorization conventions.

## Installation And Configuration

```bash
composer require franbarbalopez/mirror
```

Publish the config only when the application needs to customize TTL or session storage:

```bash
php artisan vendor:publish --tag=mirror
```

Published config file: `config/mirror.php`.

| Config key | Description |
|------------|-------------|
| `ttl` | Maximum impersonation age in seconds. Default: `1800`. Set to `null` to disable expiration. |
| `session.key` | Session namespace for the signed impersonation payload and signature. Default env fallback: `MIRROR_SESSION_KEY`, value `mirror.impersonation`. |

Keep TTL conservative for support/admin sessions. Avoid values above 60 minutes unless the product explicitly requires it.

## Impersonatable Models

Models involved in impersonation should implement `Mirror\Contracts\Impersonatable`.

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

`canImpersonate()` is evaluated on the authenticated impersonator. `canBeImpersonated()` is evaluated on the target user.

Keep authorization in application code explicit. UI hiding is not enough; protect the start route with middleware, policies, or gates that match the application's security model.

## Facade API

```php
use Mirror\Facades\Mirror;

Mirror::impersonate($user);
Mirror::impersonate($user, guard: 'web');
Mirror::impersonate($user, context: ['ticket_id' => 123]);
Mirror::leave();
```

`Mirror::impersonate()` accepts:

- `Authenticatable $target`: the user/model to impersonate
- `?string $guard`: optional target guard
- `array $context`: signed custom context stored with the impersonation payload

`Mirror::leave()` restores the original impersonator and returns the signed context array.

## State Helpers

```php
Mirror::active();       // bool
Mirror::expired();      // bool
Mirror::impersonator(); // ?Authenticatable
Mirror::impersonated(); // ?Authenticatable
Mirror::context();      // array
```

Use `active()` and `expired()` in middleware, controllers, or layout composers when the application needs custom behavior for active or expired impersonation sessions.

```php
if (Mirror::active() && Mirror::expired()) {
    Mirror::leave();

    return redirect()->route('login')
        ->with('status', 'Your impersonation session expired.');
}
```

## Blade Directives

Mirror registers Blade condition directives for impersonation-aware UI.

```blade
@impersonating
    <div class="rounded-md bg-yellow-100 p-3 text-yellow-900">
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

Available directives:

| Directive | Purpose |
|-----------|---------|
| `@impersonating` | Render only during active impersonation |
| `@notImpersonating` | Render only when no impersonation is active |
| `@canImpersonate` | Render when the current user implements `Impersonatable` and can impersonate |
| `@canBeImpersonated($user)` | Render when the target model implements `Impersonatable` and can be impersonated |

`@impersonating('web')` checks an active impersonation for a specific impersonated guard.

## Guard Resolution

Mirror only supports guards using Laravel's `session` driver.

The impersonator guard is resolved from the authenticated session guard. The target guard is resolved in this order:

1. Explicit `guard` argument passed to `Mirror::impersonate()`
2. Target model `guardName()` method
3. Target model `guard_name` attribute or property
4. Auth provider inference from `auth.guards`

```php
Mirror::impersonate($user, guard: 'web');
```

When multiple target guards match, Mirror uses the first matching session guard.

## Events

Mirror dispatches events when impersonation starts and stops.

```php
use Mirror\Events\ImpersonationStarted;
use Mirror\Events\ImpersonationStopped;
```

Both events expose:

- `$event->impersonator`
- `$event->impersonated`
- `$event->context`

Use events for audit logging instead of putting logging details directly in controller actions.

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

## Common Patterns

### Add Context For Auditing

```php
Mirror::impersonate($user, context: [
    'ticket_id' => $ticket->id,
    'reason' => 'support',
]);
```

The context is signed with the session payload and can be read with `Mirror::context()` while active or from the return value of `Mirror::leave()`.

### Persistent Impersonation Banner

Put an `@impersonating` banner in the authenticated layout so administrators always know they are acting as another user.

### Expiration Handling

Use `Mirror::expired()` to decide how the host application handles expired sessions. Mirror reports expiration; the application controls the response.

### Multi-Guard Applications

Pass `guard:` when the target guard is ambiguous or when the model can be authenticated by multiple session guards.

```php
Mirror::impersonate($customer, guard: 'customer');
```

## Testing

```php
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Mirror\Events\ImpersonationStarted;
use Mirror\Facades\Mirror;

use function Pest\Laravel\actingAs;

it('allows an admin to impersonate a user', function (): void {
    Event::fake();

    $admin = User::factory()->create();
    $user = User::factory()->create();

    actingAs($admin);

    Mirror::impersonate($user, context: ['reason' => 'support']);

    expect(Mirror::active())->toBeTrue()
        ->and(Mirror::impersonated()?->is($user))->toBeTrue()
        ->and(Mirror::context())->toBe(['reason' => 'support']);

    Event::assertDispatched(ImpersonationStarted::class);
});
```

Also test:

- Users who cannot impersonate
- Targets that cannot be impersonated
- The leave route restoring the original user
- Expiration behavior when the app has custom handling
- Multi-guard behavior when the app uses more than one session guard

## Common Pitfalls

- **Using GET for impersonation actions**: Start and leave routes should be POST routes with CSRF protection.
- **Only hiding UI**: Blade directives improve UX, but route/controller authorization must still enforce access.
- **Unsupported guards**: Mirror requires session guards; token/API guards are not valid impersonation targets.
- **Missing model contract**: Capability checks require models to implement `Mirror\Contracts\Impersonatable`.
- **Long-lived sessions**: Keep TTL short for support/admin workflows and handle `Mirror::expired()` deliberately.
- **No audit trail**: Listen to `ImpersonationStarted` and `ImpersonationStopped` when impersonation needs compliance or support traceability.
- **Manual auth switching**: Use Mirror's facade instead of directly logging users in/out or mutating session keys.
