# 🎭 [MotoMediaLab](https://motomedialab.com/) Impersonate

[![Latest Version on Packagist](https://img.shields.io/packagist/v/motomedialab/impersonate.svg?style=flat-square)](https://packagist.org/packages/motomedialab/impersonate)
[![Total Downloads](https://img.shields.io/packagist/dt/motomedialab/impersonate.svg?style=flat-square)](https://packagist.org/packages/motomedialab/impersonate)
![GitHub Actions](https://github.com/motomedialab/impersonate/actions/workflows/tests.yml/badge.svg)

A sleek, robust, and customisable user impersonation package for Laravel applications. This package allows administrators to securely log in as other users, aiding in replication of bugs, general support, and user management.

---

## 📦 Installation

You can install the package via Composer by running the following command in your terminal:

```bash
composer require motomedialab/impersonate
```

## ⚙️ Configuration

### 1. Publish Configuration (Optional)

You can publish the configuration file to customize the middleware groups to which the impersonation middleware is applied:

```bash
php artisan vendor:publish --tag="impersonate-config"
```

This will create a `config/impersonate.php` file where you can define the middleware groups (defaults to `['web', 'api']`).

### 2. Implement the Contracts on Your User Model(s)

To control who can initiate impersonation and who can be impersonated, your authenticatable model(s) should implement the dedicated contracts:

- **`Motomedialab\Impersonate\Contracts\CanImpersonate`**: Implemented by models permitted to initiate impersonation sessions (e.g., administrators, staff).
- **`Motomedialab\Impersonate\Contracts\CanBeImpersonated`**: Implemented by models that can be impersonated (e.g., customers, regular members).

#### Implementing Both on a Single Model

If your application uses a single `User` model for both administrators and standard users, implement both contracts:

```php
<?php

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
        // Example: Only administrators are authorised to impersonate others
        return $this->is_admin === true;
    }

    /**
     * Determine if this user can be impersonated by the impersonator.
     */
    public function canBeImpersonatedBy(CanImpersonate $user): bool
    {
        // Example: Do not allow impersonating other administrators
        return ! $this->is_admin;
    }
}
```

#### Implementing on Separate Models

If you maintain distinct models for staff and clients, you can implement only the relevant contract on each:

```php
class Admin extends Authenticatable implements CanImpersonate
{
    public function canImpersonate(CanBeImpersonated $user): bool
    {
        return true;
    }
}

class Customer extends Authenticatable implements CanBeImpersonated
{
    public function canBeImpersonatedBy(CanImpersonate $user): bool
    {
        return true;
    }
}
```

---

## 🚀 Utilisation

### Routing

The package registers two main routes automatically under the `web` and `auth` middleware groups:

1. **Begin Impersonation**: `POST` `/impersonate/{id}/{guard?}` (Route Name: `impersonate.begin`)
2. **End Impersonation**: `POST` `/impersonate/end` (Route Name: `impersonate.end`)

#### Example Blade Implementation

You can trigger impersonation from your administration dashboard using a simple form:

```html
<!-- Start Impersonating a User -->
<form action="{{ route('impersonate.begin', ['id' => $user->id]) }}" method="POST">
    @csrf
    <button type="submit">Impersonate User</button>
</form>
```

To stop impersonating and return to the administrator account, you can display a banner or button in your main layout:

```html
@if(app('impersonate')->isImpersonating())
    <div class="impersonation-banner">
        <span>You are currently logged in as {{ auth()->user()->name }}</span>
        
        <form action="{{ route('impersonate.end') }}" method="POST">
            @csrf
            <button type="submit">Return to Admin</button>
        </form>
    </div>
@endif
```

---

## 🧭 Customising Redirection Targets

For enhanced security, the package avoids accepting arbitrary redirect destinations from client request payloads. Instead, redirection targets are determined entirely server-side using a clean resolution hierarchy:

### When Beginning Impersonation

1. **Model Hook**: Define an optional `impersonationRedirectTo()` method on the target model implementing `CanBeImpersonated`:
   ```php
   class Customer extends Authenticatable implements CanBeImpersonated
   {
       public function impersonationRedirectTo(): string
       {
           return route('customer.dashboard');
       }
   }
   ```

2. **Dynamic Callback**: Register a closure in a service provider using the `Impersonate` facade:
   ```php
   use Motomedialab\Impersonate\Facades\Impersonate;

   Impersonate::redirectTo(function ($target, $actor) {
       return $target->is_vendor ? '/vendor/portal' : '/dashboard';
   });
   ```

3. **Configuration Fallback**: The `redirect_to` setting in `config/impersonate.php` (defaults to `'/'`).

### When Ending Impersonation

1. **Model Hook**: Define an optional `impersonationReturnTo()` method on your administrator model.
2. **Dynamic Callback**: Register a closure via `Impersonate::returnTo(fn ($actor) => ...)`.
3. **Captured Referrer**: Automatically returns to the previous URL where impersonation was started.
4. **Configuration Fallback**: The `return_to` setting in `config/impersonate.php` (defaults to `'/'`).

### ⚡ Invokable Actions & Customisation

The package encapsulates its key lifecycle and business workflows into invokable action classes:

- **`Motomedialab\Impersonate\Actions\BeginImpersonation`**: Validates permissions and initiates an impersonation session between two model instances (ideal for Filament, Nova, or Livewire).
- **`Motomedialab\Impersonate\Actions\EndImpersonation`**: Dispatches events and cleans up impersonation session keys.
- **`Motomedialab\Impersonate\Actions\ValidateImpersonationSession`**: Evaluates permissions dynamically on each request via the middleware.
- **`Motomedialab\Impersonate\Actions\DetermineRedirectUrl`**: Resolves post-login redirection targets.
- **`Motomedialab\Impersonate\Actions\DetermineReturnUrl`**: Resolves post-exit return destinations.

#### Programmatic Invocation (Filament / Livewire / Commands)

```php
use App\Models\User;
use Motomedialab\Impersonate\Actions\BeginImpersonation;
use Motomedialab\Impersonate\Actions\EndImpersonation;

// Initiate impersonation programmatically
app(BeginImpersonation::class)(auth()->user(), $targetUser);

// Terminate impersonation programmatically
app(EndImpersonation::class)();
```

#### Container Rebinding in Tests & Applications

Because all actions are resolved through Laravel's service container, you can rebind, extend, or mock any action:

```php
use Motomedialab\Impersonate\Actions\ValidateImpersonationSession;

// Example: Enforce custom session timeouts or tenant verification
app()->bind(ValidateImpersonationSession::class, fn () => new class {
    public function __invoke($currentUser): bool
    {
        // Custom tenant or multi-factor checks...
        return true;
    }
});
```

---

## 🔍 Checking Impersonation State

You can use the `impersonate` singleton/alias to query the current impersonation status:

```php
// Check if the current session is an impersonation session
app('impersonate')->isImpersonating(); // returns bool

// Retrieve the ID of the impersonated user
app('impersonate')->getUserId(); // returns int|null

// Retrieve the auth guard being utilised
app('impersonate')->getAuthGuard(); // returns string|null
```

---

## 🔔 Events and Auditing

Because impersonation is highly sensitive, the package dispatches events when sessions start or end, allowing you to build comprehensive audit logs for compliance:

* **`Motomedialab\Impersonate\Events\ImpersonateBegun`**: Dispatched when an impersonation session starts successfully.
  * Property `$user`: The target user being impersonated.
  * Property `$impersonatedBy`: The original administrator executing the impersonation.
* **`Motomedialab\Impersonate\Events\ImpersonateEnded`**: Dispatched when the impersonation session is ended.
  * Property `$user`: The user model that was being impersonated.

> [!IMPORTANT]
> **Audit Tip**: It is highly recommended to listen to these events and log them into your database or security log (e.g., `"User ID 1 (Admin) started impersonation session for User ID 42"`). This keeps a clear trail of administrative actions.

---

## 🆙 Upgrading from v1 to v2

### Breaking Changes

#### 1. Split Contracts
In v1, a single `Motomedialab\Impersonate\Contracts\ImpersonatableUser` contract was used for both actors and target users. In v2, this has been removed and split into two distinct, single-responsibility contracts:
- `Motomedialab\Impersonate\Contracts\CanImpersonate`: Implemented by user models authorised to initiate impersonation (e.g., `Admin`, `Staff`). Requires `canImpersonate(CanBeImpersonated $user): bool`.
- `Motomedialab\Impersonate\Contracts\CanBeImpersonated`: Implemented by user models eligible for impersonation (e.g., `User`, `Customer`). Requires `canBeImpersonatedBy(CanImpersonate $user): bool`.

If your model previously implemented `ImpersonatableUser`, update its interfaces and method signatures:

```diff
- use Motomedialab\Impersonate\Contracts\ImpersonatableUser;
+ use Motomedialab\Impersonate\Contracts\CanImpersonate;
+ use Motomedialab\Impersonate\Contracts\CanBeImpersonated;

- class User extends Authenticatable implements ImpersonatableUser
+ class User extends Authenticatable implements CanImpersonate, CanBeImpersonated
  {
-     public function canImpersonate(ImpersonatableUser $user): bool
+     public function canImpersonate(CanBeImpersonated $user): bool
      {
          return $this->is_admin;
      }

-     public function canBeImpersonatedBy(ImpersonatableUser $user): bool
+     public function canBeImpersonatedBy(CanImpersonate $user): bool
      {
          return ! $this->is_admin;
      }
  }
```

#### 2. Event Signatures
The constructor parameter types on `ImpersonateBegun` and `ImpersonateEnded` events have been updated:
- `ImpersonateBegun`: `$user` is now typed as `CanBeImpersonated` and `$impersonatedBy` is typed as `CanImpersonate`.
- `ImpersonateEnded`: `$user` is now typed as `CanBeImpersonated`.

#### 3. Configurable Route Middleware & Multi-Guard
If your application uses custom authentication guards for administrators (such as `admin` or `staff`), you can now customise the route middleware in `config/impersonate.php`:
```php
'route_middleware' => [
    'web',
    'auth:admin',
],
```

---

## 🧪 Testing

To run the package test suite, ensure you have installed the development dependencies and run:

```bash
composer test
```

---

## 📄 Licence

This package is open-source software licensed under the [MIT Licence](LICENSE).

