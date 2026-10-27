<?php

namespace App\Enums;

enum IncidentAction: string {
	case Created         = 'created';
	case StatusChanged   = 'status_changed';
	case Reopened        = 'reopened';
	case CommentAdded    = 'comment_added';
	case DuplicateMarked = 'duplicate_marked';

	/*.s. Devuelve el texto en español que representa esta acción en el historial. */
	public function label(): string {
		return match ($this) {
			self::Created => 'Incidencia creada',
			self::StatusChanged => 'Estado actualizado',
			self::Reopened => 'Incidencia reabierta',
			self::CommentAdded => 'Comentario añadido',
			self::DuplicateMarked => 'Duplicado marcado por auditoría',
		};
	}
}
