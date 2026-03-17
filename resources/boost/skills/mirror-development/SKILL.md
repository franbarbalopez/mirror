---
name: mirror-development
description: >
  Implement user impersonation in Laravel using the Mirror package — model
  configuration, routes, controllers, middleware, and audit logging. Use when
  the user says "impersonate user", "login as another user", "switch user",
  "Mirror package", "admin user switching", or needs to add user impersonation
  to a Laravel app.
---

# User Impersonation Development

Implement user impersonation in Laravel with Mirror. Administrators can temporarily log in as another user to debug issues, provide support, or verify feature behaviour.

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

```php
use Mirror\Concerns\Impersonatable;

class User extends Authenticatable
{
    use Impersonatable;

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

---

## 3 Create Routes

```php
Route::post('/admin/users/{user}/impersonate', [UserImpersonationController::class, 'start'])
    ->name('impersonation.start');

Route::post('/impersonation/leave', [UserImpersonationController::class, 'leave'])
    ->name('impersonation.leave');
```

---

## 4 Create Controller

```php
use Mirror\Facades\Mirror;

class UserImpersonationController extends Controller
{
    public function start(User $user)
    {
        Mirror::start($user);

        return redirect()->route('dashboard');
    }

    public function leave()
    {
        Mirror::stop();

        return redirect()->route('admin.users.index');
    }
}
```

---

## 5 Add Middleware

TTL middleware auto-expires impersonation sessions on admin routes:

```php
Route::middleware(['auth', 'mirror.ttl'])->group(function () {
    Route::get('/admin/users', [UserController::class, 'index']);
});
```

Prevent destructive actions during impersonation:

```php
Route::middleware('mirror.prevent')->group(function () {
    Route::post('/admin/users/{user}/delete', [UserController::class, 'destroy']);
});
```

---

## 6 Add Impersonation UI

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

## 7 Verify

1. Log in as an admin user and impersonate a test user — confirm the banner appears and `auth()->user()` returns the impersonated user
2. Click "Exit impersonation" — confirm you return as the original admin
3. Attempt a `mirror.prevent`-protected route while impersonating — confirm it is blocked

---

# Recommended Practices

- Restrict impersonation through `canImpersonate()` and `canBeImpersonated()`.
- Apply the `mirror.ttl` middleware to admin areas.
- Use `mirror.prevent` to block sensitive operations during impersonation.
- Record impersonation activity through event listeners for auditing.

---

# Events

Listen for `ImpersonationStarted` and `ImpersonationStopped` for audit logging:

```php
Event::listen(ImpersonationStarted::class, function ($event) {
    Log::info('User impersonation started', [
        'impersonator' => $event->impersonator->id,
        'impersonated' => $event->impersonated->id,
    ]);
});
```
