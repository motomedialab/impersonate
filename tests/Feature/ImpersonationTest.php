<?php

declare(strict_types=1);

use function Pest\Laravel\actingAs;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use function Pest\Laravel\withSession;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Motomedialab\Impersonate\Tests\Fixtures\User;
use Motomedialab\Impersonate\Events\ImpersonationBegun;
use Motomedialab\Impersonate\Events\ImpersonationEnded;

beforeEach(function () {
    Gate::define('impersonate', fn ($user = null) => true);
    config(['database.default' => 'sqlite']);
    config(['database.connections.sqlite' => [
        'driver' => 'sqlite',
        'database' => 'file:impersonation_test?mode=memory&cache=shared',
        'prefix' => '',
    ]]);

    config(['session.driver' => 'array']);

    // Ensure the users table exists for our dummy model
    Schema::connection('sqlite')->dropIfExists('users');
    Schema::connection('sqlite')->create('users', function (Blueprint $table) {
        $table->id();
        $table->string('email')->unique();
        $table->string('password');
        $table->boolean('can_impersonate')->default(true);
        $table->boolean('can_be_impersonated')->default(true);
        $table->timestamps();
    });

    config(['auth.providers.users.model' => User::class]);

    // register a test route to get the currently authenticated user ID.
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
        ->assertSessionHas('impersonationId', 'web::'.$user->id);

    Event::assertDispatched(ImpersonationBegun::class);
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

    Event::assertDispatched(ImpersonationEnded::class);
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
        ->assertSessionMissing('impersonationId');
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
