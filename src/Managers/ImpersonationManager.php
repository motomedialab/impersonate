<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\Managers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Application;
use Illuminate\Session\SessionManager;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Motomedialab\Impersonate\Events\ImpersonationBegun;
use Motomedialab\Impersonate\Events\ImpersonationEnded;
use Motomedialab\Impersonate\Contracts\ImpersonatableUser;
use Motomedialab\Impersonate\Exceptions\ImpersonationException;

final class ImpersonationManager
{
    private string $impersonationKey = 'impersonationId';

    private Application $app;

    public function __construct(\Closure $callback)
    {
        $this->app = $callback();
    }

    public function isImpersonating(): bool
    {
        return is_numeric($this->getUserId());
    }

    public function getUserId(): ?int
    {
        $data = $this->getSessionData();

        return array_key_exists(1, $data ?? []) ? (int) $data[1] : null;
    }

    public function getAuthGuard(): ?string
    {
        return $this->getSessionData()[0] ?? null;
    }

    public function impersonate(int $impersonationId, string $guard): void
    {
        $this->session()->put($this->impersonationKey, $guard.'::'.$impersonationId);
        auth($guard)->onceUsingId($impersonationId);
    }

    public function endImpersonation(): void
    {
        event(new ImpersonationEnded(auth()->user()));
        $this->session()->remove($this->impersonationKey);
    }

    public function beginImpersonation(Authenticatable $currentUser, int $userId, ?string $guard): void
    {
        $guard = $guard ?? config('auth.defaults.guard');
        $user = $this->findUser($userId, $guard);

        if ($this->isImpersonating()) {
            throw new ImpersonationException('An active impersonation session is already running');
        }

        // check if the current user can impersonate.
        if (! $currentUser instanceof ImpersonatableUser) {
            throw new ImpersonationException('The currently authenticated user must implement ImpersonatableUser');
        }

        // check we have a user we can impersonate
        if (! $user instanceof ImpersonatableUser) {
            throw new ImpersonationException('The provided user cannot be impersonated');
        }

        if (! $user->canBeImpersonatedBy($currentUser)) {
            throw new ImpersonationException('The provided user cannot be impersonated');
        }

        // check our current user is able to impersonate them.
        if (! $currentUser->canImpersonate($user)) {
            throw new ImpersonationException(
                'The currently authenticated user doesnt have permission to impersonate user with ID '.$userId
            );
        }

        $this->impersonate($userId, $guard);

        event(new ImpersonationBegun($user, $currentUser));
    }

    public function findUser(int $id, ?string $guard = null): ?Authenticatable
    {
        return $this->userProvider($guard)->retrieveById($id);
    }

    public function validateImpersonationSession(?Authenticatable $currentUser): bool
    {
        if (! $currentUser instanceof ImpersonatableUser) {
            return false;
        }

        $targetUser = $this->findUser($this->getUserId(), $this->getAuthGuard());

        if (! $targetUser instanceof ImpersonatableUser) {
            return false;
        }

        return $targetUser->canBeImpersonatedBy($currentUser) && $currentUser->canImpersonate($targetUser);
    }

    private function getSessionData(): ?array
    {
        try {
            $data = $this->session()->get($this->impersonationKey);

            return empty($data) ? null : explode('::', $data);
        } catch (\Throwable $e) {
            //
        }

        return null;
    }

    private function session(): SessionManager
    {
        return $this->app->get('session');
    }

    private function userProvider(?string $guard = null): UserProvider
    {
        return Auth::createUserProvider(config('auth.guards.'.$guard.'.provider'));
    }
}
