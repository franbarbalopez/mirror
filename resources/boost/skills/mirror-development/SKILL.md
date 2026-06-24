---
name: mirror-development
description: Implement user impersonation features in Laravel applications using Mirror.
---

# User Impersonation Development

This skill guides the implementation of user impersonation features in Laravel applications using Mirror.

It allows administrators to temporarily log in as another user to debug issues, provide support, or verify how features behave from a user's perspective.

---

# Steps

## 1 Install the Package

```bash
composer require franbarbalopez/mirror
```

Optionally, publish the configuration file:

```bash
php artisan vendor:publish --tag=mirror
```

---

## 2 Configure the User Model

Define the authorization rules that control who can impersonate others.

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

These methods determine whether the current user may impersonate someone else and whether a given user account is allowed to be impersonated.

---

## 3 Create Routes

Define routes for starting and stopping impersonation.

```php
Route::post('/admin/users/{user}/impersonate', [UserImpersonationController::class, 'start'])
    ->name('impersonation.start');

Route::post('/impersonation/leave', [UserImpersonationController::class, 'leave'])
    ->name('impersonation.leave');
```

---

## 4 Create Controller

Implement a controller that impersonates users and ends the impersonation session.

```php
use Mirror\Facades\Mirror;

class UserImpersonationController extends Controller
{
    public function impersonate(User $user)
    {
        Mirror::impersonate($user);

        return redirect()->route('dashboard');
    }

    public function leave()
    {
        Mirror::leave();

        return redirect()->route('admin.users.index');
    }
}
```

Mirror resolves the impersonator guard from the currently authenticated session guard. The impersonated guard can come from the explicit `guard` argument, the target model's `guardName()` method or `guard_name`, or auth provider inference:

```php
Mirror::impersonate($user, guard: 'web');
```

---

## 5 Add Impersonation UI

A simple banner can help administrators understand when they are acting as another user.

```blade
@impersonating
<div class="bg-yellow-200 p-3">
    You are impersonating {{ auth()->user()->name }}

    <form method="POST" action="{{ route('impersonation.leave') }}">
        @csrf
        <button>Exit impersonation</button>
    </form>
</div>
@endimpersonating
```

---

# Recommended Practices

- Restrict impersonation through `canImpersonate()` and `canBeImpersonated()`.
- Use `Mirror::active()` inside your own middleware or controllers when routes need custom access rules.
- Use `Mirror::expired()` when you want your application to decide the response for expired impersonations; the default `mirror.ttl` is 30 minutes and should not exceed 60 minutes.
- Record impersonation activity through event listeners for auditing.

---

# Events

The package emits events when impersonation sessions start and stop.

```php
Mirror\Events\ImpersonationStarted
Mirror\Events\ImpersonationStopped
```

Example listener:

```php
Event::listen(ImpersonationStarted::class, function ($event) {
    Log::info('User impersonation started', [
        'impersonator' => $event->impersonator->id,
        'impersonated' => $event->impersonated->id,
    ]);
});
```
