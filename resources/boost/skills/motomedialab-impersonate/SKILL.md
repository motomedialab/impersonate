---
name: motomedialab-impersonate
description: Guidelines, patterns, and best practices for implementing, configuring, and testing user impersonation using motomedialab/impersonate in Laravel applications.
---

# MotoMediaLab Impersonate Guidelines

This skill provides patterns, conventions, and architectural best practices when implementing and consuming `motomedialab/impersonate` in Laravel applications.

---

## Key Principles

1. **Role Separation (Actor vs Target)**: Impersonation involves two distinct parties:
   - **The Impersonator (Actor)**: The administrator or staff member initiating the session.
   - **The Impersonated (Target)**: The user account being viewed or diagnosed.
2. **Interface Segregation**: Authenticatable models implement dedicated contracts:
   - `CanImpersonate`: For models permitted to initiate impersonation.
   - `CanBeImpersonated`: For models eligible to be impersonated.
3. **Session Integrity**: The middleware verifies permissions dynamically on every request. If permissions are revoked mid-session, impersonation ends immediately.
4. **Safety & Auditing**: Every session start and termination dispatches events (`ImpersonateBegun`, `ImpersonateEnded`) to maintain an immutable audit trail.
5. **Server-Controlled Redirections**: Redirection targets are resolved strictly server-side (via model hooks, registered callbacks, or config defaults) rather than accepting client-provided parameters, eliminating open-redirect vulnerabilities.

---

## Contract Implementation

Models must implement one or both contracts from `Motomedialab\Impersonate\Contracts`.

### 1. Unified Model (e.g. Single `User` Model)

When your application uses a single `User` model for both administrators and regular users, implement both `CanImpersonate` and `CanBeImpersonated`:

```php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Motomedialab\Impersonate\Contracts\CanImpersonate;
use Motomedialab\Impersonate\Contracts\CanBeImpersonated;

class User extends Authenticatable implements CanImpersonate, CanBeImpersonated
{
    /**
     * Determine if this user is authorised to impersonate the target user.
     */
    public function canImpersonate(CanBeImpersonated $user): bool
    {
        // Example: Only super-administrators or support agents with permissions
        return $this->is_admin === true;
    }

    /**
     * Determine if this user can be impersonated by the actor.
     */
    public function canBeImpersonatedBy(CanImpersonate $user): bool
    {
        // Example: Prevent administrators from being impersonated
        return ! $this->is_admin;
    }
}
```

### 2. Multi-Model Architecture (e.g. `Admin` & `Customer`)

When maintaining distinct models for staff and clients, implement only the relevant contract on each model:

```php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Motomedialab\Impersonate\Contracts\CanImpersonate;
use Motomedialab\Impersonate\Contracts\CanBeImpersonated;

class Admin extends Authenticatable implements CanImpersonate
{
    public function canImpersonate(CanBeImpersonated $user): bool
    {
        return $this->hasPermissionTo('impersonate-users');
    }
}

class Customer extends Authenticatable implements CanBeImpersonated
{
    public function canBeImpersonatedBy(CanImpersonate $user): bool
    {
        // Example: Restrict impersonation if account is suspended or strictly private
        return ! $this->is_suspended;
    }
}
```

---

## Routing & HTTP Endpoints

The package registers two default routes under the `web` and `auth` middleware groups:

| Route Name | HTTP Method | URI | Description |
| :--- | :--- | :--- | :--- |
| `impersonate.begin` | `POST` | `/impersonate/{id}/{guard?}` | Initiates an impersonation session for target ID. |
| `impersonate.end` | `POST` | `/impersonate/end` | Ends current session and restores the original account. |

### Blade Implementation Patterns

#### Beginning Impersonation (Admin Panel)

```blade
<form action="{{ route('impersonate.begin', ['id' => $user->id]) }}" method="POST">
    @csrf
    <button type="submit" class="btn btn-sm btn-outline-warning">
        Impersonate {{ $user->name }}
    </button>
</form>
```

#### Ending Impersonation (Global Layout Banner)

Place an impersonation banner in your main layout (e.g. `layouts/app.blade.php`) so administrators can return to their original session from anywhere:

```blade
@if(app('impersonate')->isImpersonating())
    <div class="fixed bottom-0 left-0 right-0 bg-amber-500 text-white px-4 py-2 flex items-center justify-between z-50 shadow-lg">
        <div>
            <strong>Impersonation Active:</strong> You are browsing as <em>{{ auth()->user()->name }}</em> ({{ auth()->user()->email }}).
        </div>
        <form action="{{ route('impersonate.end') }}" method="POST">
            @csrf
            <button type="submit" class="bg-white text-amber-900 font-semibold px-3 py-1 rounded shadow hover:bg-amber-100">
                Exit Impersonation
            </button>
        </form>
    </div>
@endif
```

---

## Customising Redirection Targets

To ensure security, the package resolves destination URLs strictly on the server and ignores any client-supplied redirect parameters:

### When Starting Impersonation

1. **Model Hook**: Define an optional `impersonationRedirectTo(): string` method on the target model implementing `CanBeImpersonated`:
   ```php
   class Customer extends Authenticatable implements CanBeImpersonated
   {
       public function impersonationRedirectTo(): string
       {
           return route('customer.dashboard');
       }
   }
   ```
2. **Dynamic Callback**: Register a closure in `AppServiceProvider` using the `Impersonate` facade:
   ```php
   use Motomedialab\Impersonate\Facades\Impersonate;

    Impersonate::redirectTo(function ($target, $actor) {
        return match (true) {
            $target instanceof Vendor => route('vendor.portal'),
            $target->must_reset_password => route('password.change'),
            default => '/dashboard',
        };
    });
    ```
3. **Configuration Fallback**: `config('impersonate.redirect_to')` (defaults to `'/'`).

### When Ending Impersonation

1. **Model Hook**: Define an optional `impersonationReturnTo(): string` on the administrator model implementing `CanImpersonate`.
2. **Dynamic Callback**: Register a closure via `Impersonate::returnTo(fn ($actor) => ...)`.
3. **Captured Referrer**: Automatically redirects to the previous URL where impersonation was initiated.
4. **Configuration Fallback**: `config('impersonate.return_to')` (defaults to `'/'`).

### Invokable Actions Architecture

All core lifecycle and redirection workflows are decomposed into invokable actions:

| Action | Invocation | Description |
| :--- | :--- | :--- |
| `BeginImpersonation` | `($actor, $target, ?guard)` | Validates permissions and initiates session between actor and target models. |
| `EndImpersonation` | `()` | Ends session, dispatches `ImpersonateEnded`, clears session store. |
| `ValidateImpersonationSession` | `($currentUser)` | Validates permissions dynamically on each incoming request. |
| `DetermineRedirectUrl` | `($target, $actor)` | Resolves initial redirection destination for target user. |
| `DetermineReturnUrl` | `($actor)` | Resolves exit redirection destination for actor. |

#### Headless & Component Usage (Filament, Livewire, Artisan)

```php
use Motomedialab\Impersonate\Actions\BeginImpersonation;
use Motomedialab\Impersonate\Actions\EndImpersonation;

// Inside a Filament table action or Livewire method:
public function impersonateUser(User $record, BeginImpersonation $impersonate): void
{
    $impersonate(auth()->user(), $record);
}

public function exitImpersonation(EndImpersonation $endImpersonation): void
{
    $endImpersonation();
}
```

#### Container Rebinding for Custom Domain Logic

```php
use Motomedialab\Impersonate\Actions\ValidateImpersonationSession;

// Enforce custom multi-tenant session checks or time limits:
app()->bind(ValidateImpersonationSession::class, fn () => new class {
    public function __invoke($currentUser): bool
    {
        // Custom tenant / IP / timeout check...
        return true;
    }
});
```

---

## Checking Impersonation State

Query impersonation status via the `Impersonate` facade or `app('impersonate')`:

```php
use Motomedialab\Impersonate\Facades\Impersonate;

// Check if currently impersonating another account
if (Impersonate::isImpersonating()) {
    $targetId = Impersonate::getUserId();       // int|null
    $activeGuard = Impersonate::getAuthGuard(); // string|null
}
```

---

## Audit Logging & Events

Listen to the package events to store audit logs in your database or security monitoring service:

```php
namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Motomedialab\Impersonate\Events\ImpersonateBegun;
use Motomedialab\Impersonate\Events\ImpersonateEnded;

class LogImpersonationActivity
{
    public function handleImpersonateBegun(ImpersonateBegun $event): void
    {
        Log::warning('Impersonation started', [
            'admin_id' => $event->impersonatedBy->getAuthIdentifier(),
            'target_id' => $event->user->getAuthIdentifier(),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function handleImpersonateEnded(ImpersonateEnded $event): void
    {
        Log::info('Impersonation ended', [
            'target_id' => $event->user->getAuthIdentifier(),
        ]);
    }
}
```

---

## Testing Guidelines

When testing impersonation flows in applications, use Pest or PHPUnit:

```php
use Illuminate\Support\Facades\Event;
use Motomedialab\Impersonate\Events\ImpersonateBegun;
use Motomedialab\Impersonate\Events\ImpersonateEnded;

it('allows authorised admin to impersonate customer', function () {
    Event::fake();

    $admin = Admin::factory()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($admin)
        ->post(route('impersonate.begin', ['id' => $customer->id]))
        ->assertRedirect('/dashboard')
        ->assertSessionHas('impersonationId', 'web::' . $customer->id);

    Event::assertDispatched(ImpersonateBegun::class, function ($event) use ($customer, $admin) {
        return $event->user->id === $customer->id
            && $event->impersonatedBy->id === $admin->id;
    });
});

it('allows ending an active impersonation session', function () {
    Event::fake();

    $customer = Customer::factory()->create();

    $this->withSession(['impersonationId' => 'web::' . $customer->id])
        ->actingAs($customer)
        ->post(route('impersonate.end'))
        ->assertRedirect()
        ->assertSessionMissing('impersonationId');

    Event::assertDispatched(ImpersonateEnded::class);
});
```
