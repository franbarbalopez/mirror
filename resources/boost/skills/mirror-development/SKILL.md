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
        Mirror::stop();

        return redirect()->route('admin.users.index');
    }
}
```

Mirror resolves the impersonator guard from the currently authenticated session guard. The impersonated guard can come from the explicit `guard` argument, the target model's `guardName()` method or `guard_name`, or auth provider inference:

```php
Mirror::impersonate($user, guard: 'web');
```

---

## 5 Add Middleware

Protect administrative routes with the TTL middleware.

```php
Route::middleware(['auth', 'mirror.ttl'])->group(function () {
    Route::get('/admin/users', [UserController::class, 'index']);
});
```

Prevent destructive actions while an impersonation session is active.

```php
Route::middleware('mirror.prevent')->group(function () {
    Route::post('/admin/users/{user}/delete', [UserController::class, 'destroy']);
});
```

---

## 6 Add Impersonation UI

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
- Apply the `mirror.ttl` middleware to admin areas.
- Use `mirror.prevent` to block sensitive operations during impersonation.
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
