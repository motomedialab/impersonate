<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Motomedialab\Impersonate\Controllers\ImpersonationController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::post('impersonate/end', [ImpersonationController::class, 'end'])
        ->name('impersonate.end');

    Route::post('impersonate/{id}/{guard?}', [ImpersonationController::class, 'begin'])
        ->where('id', '[0-9]+')
        ->name('impersonate.begin');
});
