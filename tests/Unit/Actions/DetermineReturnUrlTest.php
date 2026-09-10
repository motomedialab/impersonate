<?php

declare(strict_types=1);

use Motomedialab\Impersonate\Facades\Impersonate;
use Motomedialab\Impersonate\Tests\Fixtures\User;
use Motomedialab\Impersonate\Actions\DetermineReturnUrl;
use Motomedialab\Impersonate\Managers\ImpersonationManager;
use Motomedialab\Impersonate\Tests\Fixtures\CustomRedirectUser;

beforeEach(function () {
    Impersonate::flushCallbacks();
});

it('returns the model defined return destination if method exists', function () {
    $actor = CustomRedirectUser::create(['email' => 'admin@example.com', 'password' => 'secret']);

    $action = app(DetermineReturnUrl::class);

    expect($action($actor))->toBe('/model-return');
});

it('prioritises callback over config and referrer', function () {
    $actor = User::create(['email' => 'admin@example.com', 'password' => 'secret']);

    $manager = app(ImpersonationManager::class);
    $manager->setReferrer('/captured-referrer');
    config(['impersonate.return_to' => '/config-return']);

    Impersonate::returnTo(fn ($a) => '/callback-return-'.$a->id);

    $action = app(DetermineReturnUrl::class);

    expect($action($actor))->toBe('/callback-return-'.$actor->id);
});

it('prioritises config over captured referrer', function () {
    $actor = User::create(['email' => 'admin@example.com', 'password' => 'secret']);

    $manager = app(ImpersonationManager::class);
    $manager->setReferrer('/captured-referrer');
    config(['impersonate.return_to' => '/config-return']);

    $action = app(DetermineReturnUrl::class);

    expect($action($actor))->toBe('/config-return');
});

it('returns captured referrer if config is not set', function () {
    $actor = User::create(['email' => 'admin@example.com', 'password' => 'secret']);

    $manager = app(ImpersonationManager::class);
    $manager->setReferrer('/captured-referrer');
    config(['impersonate.return_to' => null]);

    $action = app(DetermineReturnUrl::class);

    expect($action($actor))->toBe('/captured-referrer');
});

it('falls back to root slash if nothing is specified', function () {
    $actor = User::create(['email' => 'admin@example.com', 'password' => 'secret']);

    config(['impersonate.return_to' => null]);

    $action = app(DetermineReturnUrl::class);

    expect($action($actor))->toBe('/');
});
