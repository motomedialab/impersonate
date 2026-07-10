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

### 2. Implement the Contract on Your User Model

To control who can impersonate others, and who can be impersonated, your `User` model (or authenticatable model) must implement the `Motomedialab\Impersonate\Contracts\ImpersonatableUser` contract. 

This contract requires the implementation of two methods:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Motomedialab\Impersonate\Contracts\ImpersonatableUser;

class User extends Authenticatable implements ImpersonatableUser
{
    /**
     * Determine if this user is authorised to impersonate the target user.
     */
    public function canImpersonate(ImpersonatableUser $user): bool
    {
        // Example: Only admins are authorised to impersonate others
        return $this->is_admin === true;
    }

    /**
     * Determine if this user can be impersonated by the impersonator.
     */
    public function canBeImpersonatedBy(ImpersonatableUser $user): bool
    {
        // Example: Do not allow impersonating other admins
        return ! $this->is_admin;
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

## 🧪 Testing

To run the package test suite, ensure you have installed the development dependencies and run:

```bash
composer test
```

---

## 📄 Licence

This package is open-source software licensed under the [MIT Licence](LICENSE).
