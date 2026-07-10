<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        if (Gate::denies('impersonate')) {
            abort(403);
        }

        try {
            $driver->beginImpersonation($request->user(), $id, $guard);
        } catch (ImpersonationException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect('/', 302);
    }

    public function end(ImpersonationManager $driver): RedirectResponse
    {
        if (! $driver->isImpersonating()) {
            return redirect()->back();
        }

        $driver->endImpersonation();

        return redirect()->back();
    }
}
