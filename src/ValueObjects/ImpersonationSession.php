<?php

declare(strict_types=1);

namespace Motomedialab\Impersonate\ValueObjects;

final readonly class ImpersonationSession
{
    public function __construct(
        public int $targetId,
        public string $targetGuard,
        public ?int $actorId = null,
        public ?string $actorGuard = null,
    ) {
    }

    /**
     * Create an instance from session data (supporting arrays or legacy delimited strings).
     */
    public static function fromSession(mixed $data): ?self
    {
        if (is_array($data)) {
            if (! isset($data['target_id'], $data['target_guard']) || ! is_numeric($data['target_id'])) {
                return null;
            }

            return new self(
                targetId: (int) $data['target_id'],
                targetGuard: (string) $data['target_guard'],
                actorId: isset($data['actor_id']) && is_numeric($data['actor_id']) ? (int) $data['actor_id'] : null,
                actorGuard: isset($data['actor_guard']) && is_string($data['actor_guard']) ? $data['actor_guard'] : null,
            );
        }

        if (is_string($data) && $data !== '') {
            $parts = explode('::', $data);

            // Format: "guard::targetId" or "guard::targetId::actorGuard::actorId"
            if (count($parts) >= 2 && is_numeric($parts[1])) {
                $targetGuard = $parts[0];
                $targetId = (int) $parts[1];
                $actorGuard = $parts[2] ?? null;
                $actorId = isset($parts[3]) && is_numeric($parts[3]) ? (int) $parts[3] : null;

                return new self(
                    targetId: $targetId,
                    targetGuard: $targetGuard,
                    actorId: $actorId,
                    actorGuard: $actorGuard !== null && $actorGuard !== '' ? $actorGuard : null,
                );
            }
        }

        return null;
    }

    /**
     * Convert the session state to an array representation for session storage.
     *
     * @return array{target_id: int, target_guard: string, actor_id: int|null, actor_guard: string|null}
     */
    public function toArray(): array
    {
        return [
            'target_id' => $this->targetId,
            'target_guard' => $this->targetGuard,
            'actor_id' => $this->actorId,
            'actor_guard' => $this->actorGuard,
        ];
    }
}
