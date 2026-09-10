<?php

declare(strict_types=1);

use Motomedialab\Impersonate\Facades\Impersonate;
use Motomedialab\Impersonate\Tests\Fixtures\User;
use Motomedialab\Impersonate\Actions\DetermineRedirectUrl;
use Motomedialab\Impersonate\Tests\Fixtures\CustomRedirectUser;

beforeEach(function () {
    Impersonate::flushCallbacks();
});

it('returns the model defined redirect destination if method exists', function () {
    $actor = User::create(['email' => 'admin@example.com', 'password' => 'secret']);
    $target = CustomRedirectUser::create(['email' => 'target@example.com', 'password' => 'secret']);

    $action = app(DetermineRedirectUrl::class);

    expect($action($target, $actor))->toBe('/model-redirect');
});

it('prioritises callback over config fallback', function () {
    $actor = User::create(['email' => 'admin@example.com', 'password' => 'secret']);
    $target = User::create(['email' => 'target@example.com', 'password' => 'secret']);

    Impersonate::redirectTo(fn ($t, $a) => '/callback-target-'.$t->id.'-actor-'.$a->id);

    $action = app(DetermineRedirectUrl::class);

    expect($action($target, $actor))->toBe('/callback-target-'.$target->id.'-actor-'.$actor->id);
});

it('falls back to configured redirect_to setting', function () {
    config(['impersonate.redirect_to' => '/default-dashboard']);

    $actor = User::create(['email' => 'admin@example.com', 'password' => 'secret']);
    $target = User::create(['email' => 'target@example.com', 'password' => 'secret']);

    $action = app(DetermineRedirectUrl::class);

    expect($action($target, $actor))->toBe('/default-dashboard');
});

it('falls back to root slash if config is not set', function () {
    config(['impersonate.redirect_to' => null]);

    $actor = User::create(['email' => 'admin@example.com', 'password' => 'secret']);
    $target = User::create(['email' => 'target@example.com', 'password' => 'secret']);

    $action = app(DetermineRedirectUrl::class);

    expect($action($target, $actor))->toBe('/');
});
