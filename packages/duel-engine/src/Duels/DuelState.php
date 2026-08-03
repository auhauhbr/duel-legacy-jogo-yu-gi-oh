<?php

declare(strict_types=1);

namespace DuelLegacy\DuelEngine\Duels;

use DuelLegacy\DuelEngine\Phases\DuelPhase;
use DuelLegacy\DuelEngine\Players\DuelPlayerState;
use DuelLegacy\DuelEngine\Random\DeterministicRngState;

final readonly class DuelState
{
    /**
     * @param  list<DuelPlayerState>  $players
     * @param  list<string>  $turnOrder
     */
    public function __construct(
        public string $duelId,
        public string $rulesProfileId,
        public string $engineVersion,
        public string $cardPoolVersion,
        public array $players,
        public array $turnOrder,
        public ?DeterministicRngState $rngState,
        public DuelStatus $status,
        public int|float $turnNumber,
        public ?string $currentPlayerId,
        public ?DuelPhase $phase,
        public ?string $winnerId,
        public ?DuelResultReason $resultReason,
    ) {}

    /** @param array<string, mixed> $changes */
    public function with(array $changes): self
    {
        return new self(
            duelId: $changes['duelId'] ?? $this->duelId,
            rulesProfileId: $changes['rulesProfileId'] ?? $this->rulesProfileId,
            engineVersion: $changes['engineVersion'] ?? $this->engineVersion,
            cardPoolVersion: $changes['cardPoolVersion'] ?? $this->cardPoolVersion,
            players: $changes['players'] ?? $this->players,
            turnOrder: $changes['turnOrder'] ?? $this->turnOrder,
            rngState: array_key_exists('rngState', $changes) ? $changes['rngState'] : $this->rngState,
            status: $changes['status'] ?? $this->status,
            turnNumber: $changes['turnNumber'] ?? $this->turnNumber,
            currentPlayerId: array_key_exists('currentPlayerId', $changes) ? $changes['currentPlayerId'] : $this->currentPlayerId,
            phase: array_key_exists('phase', $changes) ? $changes['phase'] : $this->phase,
            winnerId: array_key_exists('winnerId', $changes) ? $changes['winnerId'] : $this->winnerId,
            resultReason: array_key_exists('resultReason', $changes) ? $changes['resultReason'] : $this->resultReason,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'duelId' => $this->duelId,
            'rulesProfileId' => $this->rulesProfileId,
            'engineVersion' => $this->engineVersion,
            'cardPoolVersion' => $this->cardPoolVersion,
            'players' => array_map(static fn (DuelPlayerState $player): array => $player->toArray(), $this->players),
            'turnOrder' => $this->turnOrder,
            'rngState' => $this->rngState?->toArray(),
            'status' => $this->status->value,
            'turnNumber' => $this->turnNumber,
            'currentPlayerId' => $this->currentPlayerId,
            'phase' => $this->phase?->value,
            'winnerId' => $this->winnerId,
            'resultReason' => $this->resultReason?->value,
        ];
    }
}
