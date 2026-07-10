<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Motomedialab\Impersonate\Managers\ImpersonationManager;
use Motomedialab\Impersonate\Exceptions\ImpersonationException;

final class ImpersonationController
{
    /**
     * Begin an impersonation session.
     */
    public function begin(Request $request, ImpersonationManager $driver, int $id, ?string $guard = null): RedirectResponse
    {
        try {
            $driver->beginImpersonation($request->user(), $id, $guard);
        } catch (ImpersonationException $impersonationException) {
            return back()->withErrors(['error' => $impersonationException->getMessage()]);
        }

        return redirect('/', 302);
    }

    public function end(ImpersonationManager $driver): RedirectResponse
    {
        if (! $driver->isImpersonating()) {
            return back();
        }

        $driver->endImpersonation();

        return back();
    }
}
