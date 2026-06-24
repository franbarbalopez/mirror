# Mirror

Mirror is a Laravel package that provides secure user impersonation. It allows administrators to temporarily log in as another user to debug issues, provide support, or test user experiences.

The package protects impersonation sessions using HMAC-SHA256 verification, configurable TTL status checks, and lifecycle events for audit logging. It also supports multi-guard authentication and includes Blade directives for UI awareness.

---

# Core Concepts

## Starting Impersonation

An impersonation session begins by calling the `Mirror::impersonate()` method with the target user.

@verbatim
<code-snippet name="Start impersonation" lang="php">
use Mirror\Facades\Mirror;

public function impersonate(User $user)
{
    Mirror::impersonate($user);

    return redirect()->route('dashboard');
}
</code-snippet>
@endverbatim

The impersonator guard is resolved from the currently authenticated session guard. The impersonated guard can be passed explicitly with `guard`, read from the target model's `guardName()` method or `guard_name`, or inferred from the target model's auth provider.

@verbatim
<code-snippet name="Start with explicit guard" lang="php">
Mirror::impersonate($user, guard: 'web');
</code-snippet>
@endverbatim

---

## Stopping Impersonation

To return to the original user, call `Mirror::leave()`.

@verbatim
<code-snippet name="Stop impersonation" lang="php">
use Mirror\Facades\Mirror;

public function leave()
{
    Mirror::leave();

    return redirect()->route('admin.users.index');
}
</code-snippet>
@endverbatim

If the impersonation has expired according to `Mirror::expired()`, `leave()` still restores the original user while verifying session integrity.

---

# User Model Configuration

Your user model should define the authorization logic that determines who can impersonate others.

A contract is provided:

`Mirror\Contracts\Impersonatable`

Implement the following methods to define your own rules.

@verbatim
<code-snippet name="Impersonatable user model" lang="php">
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
</code-snippet>
@endverbatim

Both the impersonator and impersonated models must implement `Impersonatable`.

---

# Checking Impersonation State

The facade exposes helper methods for determining whether impersonation is active.

@verbatim
<code-snippet name="Check impersonation state" lang="php">
Mirror::active();

Mirror::expired();

Mirror::impersonator();
</code-snippet>
@endverbatim

These methods are commonly used inside controllers, middleware, or views.

The default `mirror.ttl` is 30 minutes. Avoid values above 60 minutes. Use `Mirror::expired()` in your application code to decide how to handle expired impersonations.

---

# Blade Directives

Blade directives are provided to make UI behavior easier.

## Impersonation Banner

@verbatim
<code-snippet name="Blade impersonation directive" lang="blade">
@impersonating
    <div class="alert">
        You're impersonating {{ auth()->user()->name }}.
    </div>
@endimpersonating
</code-snippet>
@endverbatim

---

## Authorization Checks

@verbatim
<code-snippet name="Blade impersonation permissions" lang="blade">
@canImpersonate
    <a href="{{ route('admin.users.index') }}">Manage Users</a>
@endcanImpersonate

@canBeImpersonated($user)
    <form method="POST" action="{{ route('impersonation.start', $user) }}">
        @csrf
        <button>Impersonate</button>
    </form>
@endcanBeImpersonated
</code-snippet>
@endverbatim

---

# Events

Two events are dispatched when impersonation begins or ends:

- `Mirror\Events\ImpersonationStarted`
- `Mirror\Events\ImpersonationStopped`

@verbatim
<code-snippet name="Impersonation event listener" lang="php">
use Mirror\Events\ImpersonationStarted;

Event::listen(ImpersonationStarted::class, function ($event) {
    Log::info('User impersonation started', [
        'impersonator_id' => $event->impersonator->id,
        'impersonated_id' => $event->impersonated->id,
        'context' => $event->context,
    ]);
});
</code-snippet>
@endverbatim

# Best Practices

- Define `canImpersonate()` and `canBeImpersonated()` on your user model.
- Use `Mirror::active()` inside your own middleware or controllers when routes need custom access rules.
- Use `Mirror::expired()` when you need custom expiration responses.
- Listen to impersonation events for audit logging.
- Prefer the facade API instead of manually switching authentication contexts.

---

# Security

Impersonation sessions are protected through:

- HMAC-SHA256 session integrity verification
- Guard-aware authentication restoration
- Configurable TTL status checks

If session tampering is detected, the session is immediately terminated and cleared.
