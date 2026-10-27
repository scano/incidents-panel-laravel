<?php

namespace App\Enums;

enum IncidentStatus: string
{
    case Open = 'abierta';
    case InReview = 'en_revision';
    case Blocked = 'bloqueada';
    case Resolved = 'resuelta';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Abierta',
            self::InReview => 'En revisión',
            self::Blocked => 'Bloqueada',
            self::Resolved => 'Resuelta',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open, self::Blocked => [self::InReview],
            self::InReview => [self::Blocked, self::Resolved],
            self::Resolved => [self::Open],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
