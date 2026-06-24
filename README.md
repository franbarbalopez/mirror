<img src="art/logo.png" alt="Mirror Logo">

<div align="center">
    <img alt="Latest Version on Packagist" src="https://img.shields.io/packagist/v/franbarbalopez/mirror.svg">
    <img alt="GitHub Tests Action Status" src="https://img.shields.io/github/actions/workflow/status/franbarbalopez/mirror/tests.yml?label=tests">
    <img alt="Total Downloads" src="https://img.shields.io/packagist/dt/franbarbalopez/mirror.svg">
    <img alt="License" src="https://img.shields.io/packagist/l/franbarbalopez/mirror.svg">
</div>

# Mirror

Mirror is an elegant user impersonation package for Laravel. It allows administrators to seamlessly log in as other users to troubleshoot issues, provide support, or test user experiences. Mirror handles session integrity with cryptographic verification, multi-guard support, signed context, and lifecycle events for audit logging. Perfect for production applications that need reliable and secure user impersonation without forcing route or response decisions.

## Features

- HMAC-SHA256 session integrity to prevent tampering
- Multi-guard support
- Signed free-form impersonation context
- Configurable TTL status checks
- Lifecycle events for audit logging

## Requirements

- PHP 8.2+
- Laravel 11+

## Installation

```bash
composer require franbarbalopez/mirror
```

Optional - publish the config file:

```bash
php artisan vendor:publish --tag=mirror
```

## Quick Start

### 1. Implement the Impersonatable Contract

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

**Important:** models participating in impersonation must implement `Impersonatable`.

### 2. Start Impersonating

```php
use Mirror\Facades\Mirror;

public function impersonate(User $user)
{
    Mirror::impersonate($user);

    return redirect()->route('dashboard');
}
```

### 3. Stop Impersonating

```php
use Mirror\Facades\Mirror;

public function leave()
{
    Mirror::leave();

    return redirect()->route('admin.users.index');
}
```

## Security

Impersonation sessions are protected with HMAC-SHA256 hashes using your app key. The hash covers the impersonator ID, guard names, start time, target user ID, and custom context. On every `leave()` call, Mirror verifies this hash - if someone's tampered with the session, it throws an exception and clears everything.

Mirror reports whether an active impersonation has expired using `ttl` in `config/mirror.php`. The default TTL is 30 minutes. Mirror does not automatically close expired sessions or choose an HTTP response for your application.

## API Reference

### Starting Impersonation

By user instance:

```php
Mirror::impersonate($user);

// With an explicit target guard and custom context
Mirror::impersonate(
    target: $targetUser,
    guard: 'web',
    context: [
        'reason' => 'Support request',
        'ticket_id' => 123,
    ],
);
```

Mirror resolves guards this way:

- The impersonator guard is the currently authenticated session guard.
- The target guard is the explicit `guard` argument when provided.
- Without `guard`, Mirror uses the target model's `guardName()` method, `guard_name` attribute, or `guard_name` default property when present.
- Finally, Mirror infers the guard from the target model's auth provider.
- If multiple session guards match the same model, Mirror uses the first matching guard.

### Stopping Impersonation

```php
$context = Mirror::leave();
```

`leave()` verifies the signed session payload before restoring the original user, then returns the impersonation context after the session state has been cleared.

### Checking State

```php
Mirror::active(): bool
Mirror::expired(): bool
Mirror::impersonator(): ?Authenticatable
Mirror::impersonated(): ?Authenticatable
Mirror::context(): array
```

### Checking Expiration

Mirror can tell you whether the current impersonation has exceeded `config('mirror.ttl')`, but your application decides what to do next:

```php
if (Mirror::active() && Mirror::expired()) {
    $context = Mirror::leave();

    return redirect()
        ->route('admin.users.index')
        ->with('warning', __('Impersonation expired.'));
}
```

The default `mirror.ttl` is `1800` seconds. Set it to `null` to make `Mirror::expired()` always return `false`.

## Exceptions

All Mirror domain exceptions extend `Mirror\Exceptions\MirrorException`, so you can catch every package error from one base type or handle specific failures individually.

```php
use Mirror\Exceptions\MirrorException;
use Mirror\Exceptions\UnsupportedGuard;
use Mirror\Facades\Mirror;

try {
    Mirror::impersonate($user);
} catch (UnsupportedGuard $exception) {
    report($exception->getMessage());
} catch (MirrorException $exception) {
    report($exception->getMessage());
}
```

| Exception | Meaning |
| --- | --- |
| `CanNotImpersonate` | The authenticated user does not implement the impersonation contract or `canImpersonate()` returned `false`. |
| `CanNotBeImpersonated` | The target user does not implement the impersonation contract or `canBeImpersonated()` returned `false`. |
| `ImpersonationAlreadyActive` | A session is already impersonating another user. |
| `ImpersonationNotActive` | `leave()` was called without an active impersonation. |
| `TamperedImpersonationState` | The signed session payload is missing or invalid; Mirror clears the impersonation state for safety. |
| `UnsupportedGuard` | Mirror could not infer a Laravel session guard, the selected guard is not a Laravel session guard, or no authenticated session guard exists. |

## Authorization

The `Impersonatable` contract defines the two authorization methods Mirror requires:

```php
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

Both the logged-in impersonator and the target user must implement the contract:

```php
class User extends Authenticatable implements Impersonatable
{
    public function canImpersonate(): bool
    {
        return $this->hasPermission('impersonate-users');
    }

    public function canBeImpersonated(): bool
    {
        return ! $this->is_system_account;
    }
}
```

## Impersonation Context

Mirror lets you attach a free-form array context to the signed impersonation payload. Use it for your own application data, such as support reasons, ticket IDs, workflow sources, or audit metadata.

```php
public function impersonate(User $user)
{
    Mirror::impersonate(
        target: $user,
        context: [
            'reason' => request('reason'),
            'ticket_id' => request('ticket_id'),
            'source' => 'admin-panel',
        ],
    );

    return redirect()->route('dashboard');
}

public function leave()
{
    $context = Mirror::leave();

    audit('Impersonation ended', $context);

    return redirect()->route('admin.users.index');
}
```

The context is available while impersonation is active through `Mirror::context()`. It is also returned by `Mirror::leave()` and available in lifecycle events through `$event->context`.

Mirror does not reserve any context keys.

## Events

Mirror dispatches two events you can listen to:

- `Mirror\Events\ImpersonationStarted`
- `Mirror\Events\ImpersonationStopped`

Each event contains the impersonator, the impersonated user, and the custom context. Good for audit logs or triggering workflows.

```php
use Mirror\Events\ImpersonationStarted;

Event::listen(ImpersonationStarted::class, function (ImpersonationStarted $event) {
    // Log the activity to your audit system of choice
    Log::info('User impersonation started', [
        'impersonator_id' => $event->impersonator->id,
        'impersonated_id' => $event->impersonated->id,
        'context' => $event->context,
    ]);
});
```

## Performance & Optimization

Mirror keeps the core impersonation state in one signed session payload and delegates guard resolution, guard validation, and storage to focused classes.

## Multi-Guard Support

Mirror resolves the impersonator guard from the currently authenticated session guard:

```php
Auth::guard('admin')->login($admin);

Mirror::impersonate($user); // uses 'admin' as the impersonator guard

Mirror::leave(); // restores to 'admin' guard
```

For the target user, Mirror uses the explicit `guard` argument or model/provider inference. If the same model is attached to multiple session guards, Mirror uses the first matching guard. Pass the target guard explicitly when you need a specific one:

```php
Mirror::impersonate($user, guard: 'web');
```

## Blade Directives

**@impersonating**

```blade
@impersonating
    <div class="alert">
        You're impersonating {{ auth()->user()->name }}.
        <a href="{{ route('impersonation.leave') }}">Exit</a>
    </div>
@endimpersonating

{{-- Check specific guard --}}
@impersonating('admin')
    <div>Impersonating via admin guard</div>
@endimpersonating
```

**@canImpersonate**

```blade
@canImpersonate
    <a href="{{ route('admin.users.index') }}">Manage Users</a>
@endcanImpersonate

{{-- With guard --}}
@canImpersonate('admin')
    <div>Admin tools</div>
@endcanImpersonate
```

**@canBeImpersonated**

```blade
{{-- Check current user --}}
@canBeImpersonated
    <span>Available for support</span>
@endcanBeImpersonated

{{-- Check specific user --}}
@canBeImpersonated($user)
    <form method="POST" action="{{ route('impersonation.start', $user) }}">
        @csrf
        <button>Impersonate</button>
    </form>
@endcanBeImpersonated

{{-- With guard --}}
@canBeImpersonated($user, 'admin')
    <button>Login as this user</button>
@endcanBeImpersonated
```

## License

MIT. See [LICENSE.md](LICENSE.md).

## Credits

Developed by [franbarbalopez](https://github.com/franbarbalopez).
