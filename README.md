<img src="art/logo.png" alt="Mirror Logo">

<div align="center">
    <img alt="Latest Version on Packagist" src="https://img.shields.io/packagist/v/franbarbalopez/mirror.svg">
    <img alt="GitHub Tests Action Status" src="https://img.shields.io/github/actions/workflow/status/franbarbalopez/mirror/tests.yml?label=tests">
    <img alt="Total Downloads" src="https://img.shields.io/packagist/dt/franbarbalopez/mirror.svg">
    <img alt="License" src="https://img.shields.io/packagist/l/franbarbalopez/mirror.svg">
</div>

# Mirror

Mirror is an elegant user impersonation package for Laravel. It allows administrators to seamlessly log in as other users to troubleshoot issues, provide support, or test user experiences. Mirror handles session integrity with cryptographic verification, automatic expiration, multi-guard support, flexible middleware, and lifecycle events for audit logging. Perfect for production applications that need reliable and secure user impersonation.

## Features

- HMAC-SHA256 session integrity to prevent tampering
- Configurable TTL expiration
- Middleware for access control and TTL enforcement
- Multi-guard support
- Flexible URL redirection
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

Impersonation sessions are protected with HMAC-SHA256 hashes using your app key. The hash covers the impersonator ID, guard name, start time, and redirect URL. On every `leave()` call, Mirror verifies this hash - if someone's tampered with the session, it throws an exception and clears everything.

Configure TTL in `config/mirror.php` to automatically expire sessions after a set time.

## API Reference

### Starting Impersonation

By user instance:

```php
Mirror::impersonate($user);

// With an explicit impersonated guard and a leave URL
Mirror::impersonate(
    target: $targetUser,
    guard: 'web',
    leaveUrl: route('admin.users.index'),
);
```

Mirror resolves guards this way:

- The impersonator guard is the currently authenticated session guard.
- The impersonated guard is the explicit `guard` argument when provided.
- Without `guard`, Mirror uses the impersonated model's `guardName()` method, `guard_name` attribute, or `guard_name` default property when present.
- Finally, Mirror infers the guard from the impersonated model's auth provider.
- If multiple session guards match the same model, Mirror uses the first matching guard.

### Stopping Impersonation

```php
Mirror::leave();

// Force stop - bypasses TTL check but still verifies integrity
Mirror::forceLeave();
```

Use `forceLeave()` when you need to end impersonation from admin actions or cleanup scripts - it skips the TTL check but still throws if the session's been tampered with.

### Checking State

```php
Mirror::active(): bool
Mirror::impersonator(): ?Authenticatable
Mirror::impersonated(): ?Authenticatable
Mirror::impersonatorId(): int|string|null
Mirror::leaveUrl(): ?string
```

## Middleware

### `mirror.ttl`

Checks if the impersonation session has expired and automatically leaves impersonation if needed:

```php
Route::middleware('mirror.ttl')->group(function () {
    Route::get('/admin/users', [UserController::class, 'index']);
    Route::get('/admin/users/{user}', [UserController::class, 'show']);
});
```

Good for protecting sensitive admin areas where you want expired sessions to exit gracefully. Note that when TTL expires, this middleware will end the impersonation and redirect, so make sure your session cleanup is set up properly.

### `mirror.require`

Only allows access if actively impersonating:

```php
Route::middleware('mirror.require')->group(function () {
    Route::get('/impersonation/banner', function () {
        return view('impersonation.banner');
    });
});
```

Useful for special UI components that only make sense during impersonation - like a banner showing who you're impersonating.

### `mirror.prevent`

Blocks access while impersonating:

```php
Route::middleware('mirror.prevent')->group(function () {
    Route::post('/admin/users/{user}/delete', [UserController::class, 'destroy']);
    Route::get('/admin/settings', [SettingsController::class, 'edit']);
});
```

Protects destructive actions or sensitive settings that should only be accessed as the original user, not while impersonating someone else.

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

## URL Redirection

You can control where users go when starting and stopping impersonation:

```php
public function impersonate(User $user)
{
    Mirror::impersonate(
        target: $user,
        leaveUrl: route('admin.users.index'),
    );

    return redirect()->route('dashboard');
}

public function leave()
{
    $leaveUrl = Mirror::leaveUrl();

    Mirror::leave();

    return redirect($leaveUrl ?? route('admin.users.index'));
}
```

If you don't specify `leaveUrl`, `leaveUrl()` returns `null`.

## Events

Mirror dispatches two events you can listen to:

- `Mirror\Events\ImpersonationStarted`
- `Mirror\Events\ImpersonationStopped`

Both events contain the impersonator, the target user, and the signed impersonation payload. Good for audit logs or triggering workflows.

```php
use Mirror\Events\ImpersonationStarted;

Event::listen(ImpersonationStarted::class, function (ImpersonationStarted $event) {
    // Log the activity to your audit system of choice
    Log::info('User impersonation started', [
        'impersonator_id' => $event->impersonator->id,
        'impersonated_id' => $event->impersonated->id,
        'impersonator_guard' => $event->payload->impersonatorGuard,
        'impersonated_guard' => $event->payload->impersonatedGuard,
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

For the impersonated user, Mirror uses the explicit `guard` argument or model/provider inference. If the same model is attached to multiple session guards, Mirror uses the first matching guard. Pass the target guard explicitly when you need a specific one:

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
