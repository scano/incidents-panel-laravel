<?php

namespace App\Enums;

enum IncidentStatus: string {

	case Open     = 'abierta';
	case InReview = 'en_revision';
	case Blocked  = 'bloqueada';
	case Resolved = 'resuelta';

	/*.s. Devuelve el nombre en español de este estado. */
	public function label(): string {
		return match ($this) {
			self::Open => 'Abierta',
			self::InReview => 'En revisión',
			self::Blocked => 'Bloqueada',
			self::Resolved => 'Resuelta',
		};
	}

	/*.s. Devuelve los estados a los que se permite pasar desde el estado actual. */

	public function canTransitionTo(self $target): bool {
		return in_array($target, $this->allowedTransitions(), true);
	}

	/*.s. Indica si el estado de destino está incluido entre las transiciones permitidas. */

	/** @return list<self> */
	public function allowedTransitions(): array {
		return match ($this) {
			self::Open, self::Blocked => [self::InReview],
			self::InReview => [self::Blocked, self::Resolved],
			self::Resolved => [self::Open],
		};
	}
}
