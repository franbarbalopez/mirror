<!-- prettier-ignore -->
<div align="center">

<img src="art/logo.png" alt="Mirror logo" width="220">

# Mirror

**Secure, elegant user impersonation for Laravel applications.**

[![Latest Version on Packagist](https://img.shields.io/packagist/v/franbarbalopez/mirror.svg?style=flat-square)](https://packagist.org/packages/franbarbalopez/mirror)
[![Tests](https://img.shields.io/github/actions/workflow/status/franbarbalopez/mirror/tests.yml?branch=master&label=tests&style=flat-square)](https://github.com/franbarbalopez/mirror/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/packagist/dependency-v/franbarbalopez/mirror/php?style=flat-square)](composer.json)
[![Laravel Compatibility](https://badge.laravel.cloud/badge/franbarbalopez/mirror)](https://packagist.org/packages/franbarbalopez/mirror)
[![Laravel Boost](https://badge.laravel.cloud/boost-badge.svg)](https://github.com/laravel/boost)
[![Downloads](https://img.shields.io/packagist/dt/franbarbalopez/mirror.svg?style=flat-square)](https://packagist.org/packages/franbarbalopez/mirror)

[Features](#features) | [Installation](#installation) | [Quick Start](#quick-start) | [Usage](#usage) | [Security](#security) | [Development](#development)

</div>

Mirror is a Laravel package for safely logging in as another user. It is designed for admin panels, support tooling, QA workflows, and production applications that need impersonation without handing route handling, redirects, or authorization policy decisions to a package.

Mirror stores a signed impersonation payload in the session, restores the original user when leaving, supports multiple session guards, exposes lifecycle events for audit logs, and keeps the application in control of the HTTP flow.

> [!IMPORTANT]
> Mirror only works with Laravel guards backed by the `session` driver. Token, API, and stateless guards are intentionally rejected.

## Features

- **Signed session state**: HMAC-SHA256 verification using your Laravel `app.key`.
- **Multi-guard support**: resolve the authenticated impersonator guard and infer or explicitly set the target guard.
- **Explicit authorization hooks**: require models to decide who can impersonate and who can be impersonated.
- **Custom context**: attach signed metadata such as support reasons, ticket IDs, or workflow sources.
- **TTL checks**: detect expired impersonation sessions while letting your app decide the response.
- **Blade directives**: render UI based on active impersonation and model capabilities.
- **Lifecycle events**: audit `ImpersonationStarted` and `ImpersonationStopped` events.

## Requirements

- PHP `8.2` or higher
- Laravel `11`, `12`, or `13`

## Installation

Install the package with Composer:

```bash
composer require franbarbalopez/mirror
```

Laravel auto-discovers the service provider and facade alias. If you want to customize the TTL or session namespace, publish the configuration file:

```bash
php artisan vendor:publish --tag=mirror
```

The published file is available at `config/mirror.php`.

## Quick Start

### 1. Implement the Contract

Every model that can start or receive impersonation must implement `Mirror\Contracts\Impersonatable`.

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

> [!NOTE]
> Mirror does not prescribe your authorization model. Use roles, policies, permissions, feature flags, or any business rule that fits your application.

### 2. Start Impersonating

```php
use App\Models\User;
use Mirror\Facades\Mirror;

public function impersonate(User $user)
{
    Mirror::impersonate(
        target: $user,
        context: [
            'reason' => request('reason'),
            'ticket_id' => request('ticket_id'),
        ],
    );

    return redirect()->route('dashboard');
}
```

### 3. Leave Impersonation

```php
use Mirror\Facades\Mirror;

public function leave()
{
    $context = Mirror::leave();

    audit('Impersonation ended', $context);

    return redirect()->route('admin.users.index');
}
```

## Usage

### Starting Impersonation

Use the facade to impersonate a target user. Mirror resolves the current authenticated session guard as the impersonator guard.

```php
Mirror::impersonate($user);
```

Pass a guard when the target user should be authenticated through a specific guard:

```php
Mirror::impersonate($user, guard: 'web');
```

Attach signed context when you want to carry audit metadata across the impersonation lifecycle:

```php
Mirror::impersonate(
    target: $user,
    guard: 'web',
    context: [
        'reason' => 'Support request',
        'ticket_id' => 123,
        'source' => 'admin-panel',
    ],
);
```

Mirror prevents nested impersonation and throws `ImpersonationAlreadyActive` if the current session is already impersonating another user.

### Guard Resolution

Mirror resolves guards in this order:

| Guard | Resolution |
| --- | --- |
| Impersonator guard | First authenticated Laravel guard using the `session` driver. |
| Target guard | Explicit `guard` argument, when provided. |
| Target guard | Target model `guardName()` method, `guard_name` attribute, or `guard_name` default property. |
| Target guard | First matching `session` guard whose provider model matches the target model. |

If multiple target guards match the same model, Mirror uses the first matching guard. Pass `guard:` explicitly when the choice matters.

### Reading State

```php
Mirror::active();       // bool
Mirror::expired();      // bool
Mirror::impersonator(); // ?Authenticatable
Mirror::impersonated(); // ?Authenticatable
Mirror::context();      // array
```

Use these methods to drive banners, route middleware, audit logs, or support tooling.

```php
if (Mirror::active()) {
    $impersonator = Mirror::impersonator();
    $impersonated = Mirror::impersonated();
}
```

### Expiration

The `mirror.ttl` value controls whether `Mirror::expired()` returns `true`. The default is `1800` seconds.

```php
if (Mirror::active() && Mirror::expired()) {
    $context = Mirror::leave();

    return redirect()
        ->route('admin.users.index')
        ->with('warning', __('Impersonation expired.'));
}
```

> [!TIP]
> Mirror reports expiration, but does not force logout, redirect, or abort a request. Put your preferred behavior in middleware or controller code.

Set the TTL to `null` to disable expiration checks:

```php
'ttl' => null,
```

### Blade Directives

Mirror registers Blade condition directives for common UI checks.

```blade
@impersonating
    <div class="alert">
        You are impersonating {{ auth()->user()->name }}.
        <a href="{{ route('impersonation.leave') }}">Exit</a>
    </div>
@endimpersonating

@notImpersonating
    <span>Normal session</span>
@endnotImpersonating
```

Guard-specific checks are supported:

```blade
@impersonating('web')
    <span>Impersonating through the web guard</span>
@endimpersonating
```

Capability directives call the `Impersonatable` contract methods:

```blade
@canImpersonate
    <a href="{{ route('admin.users.index') }}">Manage users</a>
@endcanImpersonate

@canBeImpersonated($user)
    <form method="POST" action="{{ route('impersonation.start', $user) }}">
        @csrf
        <button type="submit">Impersonate</button>
    </form>
@endcanBeImpersonated
```

### Events

Mirror dispatches two events:

| Event | When |
| --- | --- |
| `Mirror\Events\ImpersonationStarted` | After the target user is logged in. |
| `Mirror\Events\ImpersonationStopped` | After the original impersonator is restored. |

Both events expose `$impersonator`, `$impersonated`, and `$context`.

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

## Configuration

The default configuration is intentionally small:

```php
return [
    'ttl' => 1800,

    'session' => [
        'key' => env('MIRROR_SESSION_KEY', 'mirror.impersonation'),
    ],
];
```

| Option | Default | Description |
| --- | --- | --- |
| `ttl` | `1800` | Maximum age, in seconds, used by `Mirror::expired()`. Set to `null` to disable expiration checks. |
| `session.key` | `mirror.impersonation` | Session namespace used to store the signed payload and signature. |

## Security

Mirror stores the impersonator ID, impersonator guard, target ID, target guard, start timestamp, and context in the session. That payload is signed with HMAC-SHA256 using `config('app.key')`.

When Mirror reads impersonation state, it verifies the signature. If the payload or signature is missing or tampered with, Mirror clears the stored impersonation state and throws an exception.

Security behavior to be aware of:

- Both users must implement `Impersonatable`.
- The impersonator must return `true` from `canImpersonate()`.
- The target must return `true` from `canBeImpersonated()`.
- Only session-backed guards are accepted.
- Nested impersonation is rejected.
- Expiration is reported by `Mirror::expired()`; your application decides how to enforce it.

> [!WARNING]
> Keep your Laravel `APP_KEY` private and stable. Rotating it invalidates existing Mirror signatures, which is normally desirable for security but can interrupt active impersonation sessions.

## Exceptions

All package exceptions extend `Mirror\Exceptions\MirrorException`. For application code, the phase interfaces are usually the best catch points.

```php
use Mirror\Exceptions\CannotLeaveImpersonation;
use Mirror\Exceptions\CannotStartImpersonation;
use Mirror\Facades\Mirror;

try {
    Mirror::impersonate($user);
} catch (CannotStartImpersonation $exception) {
    report($exception);
}

try {
    Mirror::leave();
} catch (CannotLeaveImpersonation $exception) {
    report($exception);
}
```

| Phase interface | Raised by | Typical cause |
| --- | --- | --- |
| `CannotStartImpersonation` | `impersonate()` | Authorization failed, no authenticated session guard exists, target guard cannot be inferred, or impersonation is already active. |
| `CannotLeaveImpersonation` | `leave()` | No active impersonation exists or stored state is invalid. |
| `CannotReadImpersonationState` | `active()`, `expired()`, `impersonator()`, `impersonated()`, `context()` | Stored impersonation state cannot be trusted. |

Common concrete exceptions include `CanNotImpersonate`, `CanNotBeImpersonated`, `CannotInferTargetGuard`, `GuardDoesNotUseSessionDriver`, `ImpersonationAlreadyActive`, `ImpersonationNotActive`, `InvalidImpersonationSignature`, `MissingAuthenticatedSessionGuard`, and `MissingImpersonationSignature`.

## Development

Install dependencies:

```bash
composer install
```

Run the full quality suite:

```bash
composer test
```

Useful scripts:

| Command | Description |
| --- | --- |
| `composer test:lint` | Check formatting with Laravel Pint. |
| `composer lint` | Fix formatting with Laravel Pint. |
| `composer test:analyse` | Run PHPStan/Larastan. |
| `composer test:refactor` | Run Rector in dry-run mode. |
| `composer test:coverage` | Run Pest with exactly 100% coverage. |
| `composer fix` | Run Rector and Pint fixes. |
| `composer serve` | Build and serve the Orchestra Testbench workbench app. |

The test suite uses [Pest](https://pestphp.com/) and [Orchestra Testbench](https://packages.tools/testbench) to validate Mirror inside a Laravel application context.
