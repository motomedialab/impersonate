<?php

declare(strict_types=1);

use function Pest\Laravel\actingAs;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use function Pest\Laravel\withSession;
use Motomedialab\Impersonate\Facades\Impersonate;
use Motomedialab\Impersonate\Tests\Fixtures\User;
use Motomedialab\Impersonate\Events\ImpersonateBegun;
use Motomedialab\Impersonate\Events\ImpersonateEnded;
use Motomedialab\Impersonate\Tests\Fixtures\AdminUser;
use Motomedialab\Impersonate\Actions\DetermineReturnUrl;
use Motomedialab\Impersonate\Tests\Fixtures\CustomerUser;
use Motomedialab\Impersonate\Actions\DetermineRedirectUrl;
use Motomedialab\Impersonate\Managers\ImpersonationManager;
use Motomedialab\Impersonate\Tests\Fixtures\CustomRedirectUser;
use Motomedialab\Impersonate\Actions\ValidateImpersonationSession;
use Motomedialab\Impersonate\Tests\Fixtures\NonImpersonatableUser;

beforeEach(function () {
    Impersonate::flushCallbacks();

    // Register a test route to get the currently authenticated user ID.
    Route::get('test-route', fn () => auth()->id())->middleware('web');
});

it('can begin an impersonation session', function () {
    Event::fake();

    $admin = User::create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $user = User::create([
        'email' => 'user@example.com',
        'password' => 'password',
    ]);

    $this->withoutMiddleware();

    actingAs($admin)
        ->post(route('impersonate.begin', ['id' => $user->id, 'guard' => 'web']))
        ->assertRedirect('/')
        ->assertSessionHas('impersonationId', "web::{$user->id}::web::{$admin->id}");

    Event::assertDispatched(ImpersonateBegun::class);
});

it('can end an impersonation session', function () {
    Event::fake();

    $user = User::create([
        'email' => 'user@example.com',
        'password' => 'password',
    ]);

    withSession(['impersonationId' => 'web::'.$user->id])
        ->actingAs($user)
        ->post(route('impersonate.end'))
        ->assertRedirect()
        ->assertSessionMissing('impersonationId');

    Event::assertDispatched(ImpersonateEnded::class);
});

it('prevents impersonation if user cannot impersonate', function () {
    $user1 = User::create([
        'email' => 'user1@example.com',
        'password' => 'password',
        'can_impersonate' => false,
    ]);

    $user2 = User::create([
        'email' => 'user2@example.com',
        'password' => 'password',
    ]);

    $this->withoutMiddleware();

    actingAs($user1)
        ->post(route('impersonate.begin', ['id' => $user2->id]))
        ->assertSessionMissing('impersonationId')
        ->assertSessionHasErrors(['error' => 'The actor doesnt have permission to impersonate the target user']);
});

it('prevents impersonation if actor does not implement impersonation contracts', function () {
    $nonImpersonatable = NonImpersonatableUser::create([
        'email' => 'non-actor@example.com',
        'password' => 'password',
    ]);

    $user = User::create([
        'email' => 'target-user@example.com',
        'password' => 'password',
    ]);

    $this->withoutMiddleware();

    actingAs($nonImpersonatable)
        ->post(route('impersonate.begin', ['id' => $user->id]))
        ->assertSessionMissing('impersonationId')
        ->assertSessionHasErrors(['error' => 'The currently authenticated user cannot impersonate.']);
});

it('prevents impersonating a user that cannot be impersonated', function () {
    $admin = User::create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $user = User::create([
        'email' => 'user@example.com',
        'password' => 'password',
        'can_be_impersonated' => false,
    ]);

    $this->withoutMiddleware();

    actingAs($admin)
        ->post(route('impersonate.begin', ['id' => $user->id]))
        ->assertSessionMissing('impersonationId');
});

it('prevents impersonating a target that does not implement impersonation contracts', function () {
    config(['auth.providers.users.model' => NonImpersonatableUser::class]);

    $nonImpersonatable = NonImpersonatableUser::create([
        'email' => 'non-impersonatable@example.com',
        'password' => 'password',
    ]);

    $admin = User::create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $this->withoutMiddleware();

    actingAs($admin)
        ->post(route('impersonate.begin', ['id' => $nonImpersonatable->id]))
        ->assertSessionMissing('impersonationId')
        ->assertSessionHasErrors(['error' => 'The target user cannot be impersonated.']);
});

it('applies impersonation in middleware', function () {
    $admin = User::create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $user = User::create([
        'email' => 'user@example.com',
        'password' => 'password',
    ]);

    // We manually register the middleware to ensure it runs in the test
    Route::get('test-middleware', function () {
        return auth()->id();
    })->middleware(['web', Motomedialab\Impersonate\Middleware\ImpersonationMiddleware::class]);

    actingAs($admin)
        ->withSession(['impersonationId' => 'web::'.$user->id])
        ->get('test-middleware')
        ->assertSee((string) $user->id);
});

it('applies cross guard impersonation in middleware when actor is on another guard', function () {
    config(['auth.guards.admin' => [
        'driver' => 'session',
        'provider' => 'users',
    ]]);

    $admin = User::create([
        'email' => 'admin-guard@example.com',
        'password' => 'password',
    ]);

    $user = User::create([
        'email' => 'user-dealer@example.com',
        'password' => 'password',
    ]);

    Route::get('test-cross-guard-middleware', function () {
        return auth('web')->id();
    })->middleware(['web', Motomedialab\Impersonate\Middleware\ImpersonationMiddleware::class]);

    actingAs($admin, 'admin')
        ->withSession(['impersonationId' => "web::{$user->id}::admin::{$admin->id}"])
        ->get('test-cross-guard-middleware')
        ->assertSee((string) $user->id);
});

it('ends impersonation if original user permissions are revoked', function () {
    $admin = User::create([
        'email' => 'admin@example.com',
        'password' => 'password',
        'can_impersonate' => false,
    ]);

    $user = User::create([
        'email' => 'user@example.com',
        'password' => 'password',
    ]);

    Route::get('test-middleware-revoked', function () {
        return auth()->id();
    })->middleware(['web', Motomedialab\Impersonate\Middleware\ImpersonationMiddleware::class]);

    actingAs($admin)
        ->withSession(['impersonationId' => 'web::'.$user->id])
        ->get('test-middleware-revoked')
        ->assertSessionMissing('impersonationId')
        ->assertSee((string) $admin->id);
});

it('redirects back if ending impersonation when not impersonating', function () {
    $user = User::create([
        'email' => 'user@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($user)
        ->from('/previous-url')
        ->post(route('impersonate.end'))
        ->assertRedirect('/previous-url');
});

it('fails validation when current user is not impersonatable', function () {
    $user = User::create([
        'email' => 'user@example.com',
        'password' => 'password',
    ]);
    $nonImpersonatable = new NonImpersonatableUser();

    $manager = app(ImpersonationManager::class);
    $manager->impersonate($user->id, 'web');

    expect($manager->validateImpersonationSession($nonImpersonatable))->toBeFalse();
});

it('fails validation when target user does not exist or is not impersonatable', function () {
    $admin = User::create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $manager = app(ImpersonationManager::class);
    // Impersonate a non-existent user ID
    $manager->impersonate(9999, 'web');

    expect($manager->validateImpersonationSession($admin))->toBeFalse();
});

it('returns null on session exception', function () {
    $manager = new ImpersonationManager(fn () => new class () extends Illuminate\Foundation\Application {
        public function make($abstract, array $parameters = [])
        {
            throw new Exception('Session not available');
        }
        public function get($id)
        {
            throw new Exception('Session not available');
        }
    });

    expect($manager->getUserId())->toBeNull();
});

it('redirects to the configured redirect_to path when beginning impersonation', function () {
    config(['impersonate.redirect_to' => '/dashboard']);

    $admin = User::create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $user = User::create([
        'email' => 'user@example.com',
        'password' => 'password',
    ]);

    $this->withoutMiddleware();

    actingAs($admin)
        ->post(route('impersonate.begin', ['id' => $user->id, 'guard' => 'web']))
        ->assertRedirect('/dashboard');
});

it('redirects to the captured referrer URL when ending impersonation', function () {
    $user = User::create([
        'email' => 'user@example.com',
        'password' => 'password',
    ]);

    withSession([
        'impersonationId' => 'web::'.$user->id,
        'impersonatorReferrer' => '/admin/users/123/edit'
    ])
        ->actingAs($user)
        ->post(route('impersonate.end'))
        ->assertRedirect('/admin/users/123/edit');
});

it('redirects to the configured return_to path when ending impersonation', function () {
    config(['impersonate.return_to' => '/home']);

    $user = User::create([
        'email' => 'user@example.com',
        'password' => 'password',
    ]);

    withSession([
        'impersonationId' => 'web::'.$user->id,
        'impersonatorReferrer' => '/admin/users/123/edit'
    ])
        ->actingAs($user)
        ->post(route('impersonate.end'))
        ->assertRedirect('/home');
});

it('supports CanImpersonate and CanBeImpersonated split contracts', function () {
    Event::fake();

    config(['auth.providers.users.model' => CustomerUser::class]);

    $admin = AdminUser::create([
        'email' => 'admin-split@example.com',
        'password' => 'password',
    ]);

    $customer = CustomerUser::create([
        'email' => 'customer-split@example.com',
        'password' => 'password',
    ]);

    $this->withoutMiddleware();

    actingAs($admin)
        ->post(route('impersonate.begin', ['id' => $customer->id, 'guard' => 'web']))
        ->assertRedirect('/')
        ->assertSessionHas('impersonationId', "web::{$customer->id}::web::{$admin->id}");

    Event::assertDispatched(ImpersonateBegun::class, function (ImpersonateBegun $event) use ($customer, $admin) {
        return $event->user->id === $customer->id && $event->impersonatedBy->id === $admin->id;
    });

    withSession(['impersonationId' => 'web::'.$customer->id])
        ->actingAs($customer)
        ->post(route('impersonate.end'))
        ->assertRedirect()
        ->assertSessionMissing('impersonationId');

    Event::assertDispatched(ImpersonateEnded::class, function (ImpersonateEnded $event) use ($customer) {
        return $event->user->id === $customer->id;
    });
});

it('prevents split contract user from impersonating if canImpersonate returns false', function () {
    config(['auth.providers.users.model' => CustomerUser::class]);

    $admin = AdminUser::create([
        'email' => 'unauthorised-admin@example.com',
        'password' => 'password',
        'can_impersonate' => false,
    ]);

    $customer = CustomerUser::create([
        'email' => 'customer@example.com',
        'password' => 'password',
    ]);

    $this->withoutMiddleware();

    actingAs($admin)
        ->post(route('impersonate.begin', ['id' => $customer->id]))
        ->assertSessionMissing('impersonationId');
});

it('prevents impersonating split contract target if canBeImpersonatedBy returns false', function () {
    config(['auth.providers.users.model' => CustomerUser::class]);

    $admin = AdminUser::create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $customer = CustomerUser::create([
        'email' => 'protected-customer@example.com',
        'password' => 'password',
        'can_be_impersonated' => false,
    ]);

    $this->withoutMiddleware();

    actingAs($admin)
        ->post(route('impersonate.begin', ['id' => $customer->id]))
        ->assertSessionMissing('impersonationId');
});

it('ignores redirect_to parameter in request for security', function () {
    $admin = User::create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $user = User::create([
        'email' => 'user@example.com',
        'password' => 'password',
    ]);

    $this->withoutMiddleware();

    actingAs($admin)
        ->post(route('impersonate.begin', [
            'id' => $user->id,
            'redirect_to' => '/malicious-target',
        ]))
        ->assertRedirect('/');
});

it('redirects to target model hook impersonationRedirectTo', function () {
    config(['auth.providers.users.model' => CustomRedirectUser::class]);

    $admin = CustomRedirectUser::create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $user = CustomRedirectUser::create([
        'email' => 'user@example.com',
        'password' => 'password',
    ]);

    $this->withoutMiddleware();

    actingAs($admin)
        ->post(route('impersonate.begin', ['id' => $user->id]))
        ->assertRedirect('/model-redirect');
});

it('redirects using registered redirectTo callback', function () {
    $passedTarget = null;
    $passedActor = null;

    Impersonate::redirectTo(function ($target, $actor) use (&$passedTarget, &$passedActor) {
        $passedTarget = $target;
        $passedActor = $actor;

        return '/callback-redirect';
    });

    $admin = User::create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $user = User::create([
        'email' => 'user@example.com',
        'password' => 'password',
    ]);

    $this->withoutMiddleware();

    actingAs($admin)
        ->post(route('impersonate.begin', ['id' => $user->id]))
        ->assertRedirect('/callback-redirect');

    expect($passedTarget?->getAuthIdentifier())->toBe($user->id)
        ->and($passedActor?->getAuthIdentifier())->toBe($admin->id);
});

it('redirects to impersonator model hook impersonationReturnTo', function () {
    $admin = CustomRedirectUser::create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $user = User::create([
        'email' => 'user@example.com',
        'password' => 'password',
    ]);

    $manager = app(ImpersonationManager::class);

    expect($manager->getReturnUrl($admin))->toBe('/model-return');
});

it('redirects using registered returnTo callback when ending impersonation', function () {
    Impersonate::returnTo(fn () => '/callback-return');

    $user = User::create([
        'email' => 'user@example.com',
        'password' => 'password',
    ]);

    withSession(['impersonationId' => 'web::'.$user->id])
        ->actingAs($user)
        ->post(route('impersonate.end'))
        ->assertRedirect('/callback-return');
});

it('allows swapping DetermineRedirectUrl action in the container', function () {
    app()->bind(DetermineRedirectUrl::class, fn () => new class () {
        public function __invoke($target = null): string
        {
            return '/custom-bound-redirect';
        }
    });

    $admin = User::create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $user = User::create([
        'email' => 'user@example.com',
        'password' => 'password',
    ]);

    $this->withoutMiddleware();

    actingAs($admin)
        ->post(route('impersonate.begin', ['id' => $user->id]))
        ->assertRedirect('/custom-bound-redirect');
});

it('allows swapping DetermineReturnUrl action in the container', function () {
    app()->bind(DetermineReturnUrl::class, fn () => new class () {
        public function __invoke($impersonator = null, ?string $fallback = null): string
        {
            return '/custom-bound-return';
        }
    });

    $user = User::create([
        'email' => 'user@example.com',
        'password' => 'password',
    ]);

    withSession(['impersonationId' => 'web::'.$user->id])
        ->actingAs($user)
        ->post(route('impersonate.end'))
        ->assertRedirect('/custom-bound-return');
});


it('allows swapping ValidateImpersonationSession action in the container', function () {
    app()->bind(ValidateImpersonationSession::class, fn () => new class (app(ImpersonationManager::class)) {
        public function __construct(private $manager)
        {
        }
        public function __invoke(?Illuminate\Contracts\Auth\Authenticatable $actor): bool
        {
            // Custom rule: always reject validation
            return false;
        }
    });

    $admin = User::create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $manager = app(ImpersonationManager::class);
    $manager->impersonate(999, 'web');

    expect($manager->validateImpersonationSession($admin))->toBeFalse();
});

it('rejects an invalid guard when beginning impersonation', function () {
    $admin = User::create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $user = User::create([
        'email' => 'user@example.com',
        'password' => 'password',
    ]);

    $this->withoutMiddleware();

    actingAs($admin)
        ->post(route('impersonate.begin', ['id' => $user->id, 'guard' => 'nonexistent_guard']))
        ->assertSessionHasErrors(['error' => 'The specified authentication guard does not exist.']);
});
