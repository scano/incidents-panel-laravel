<?php

namespace App\Models;

use App\Enums\IncidentAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'old_value', 'new_value', 'user_name', 'metadata'];

    // .s. Log -> Usuario
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // .s. Log -> Incident
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    /* .s. Convierte la acción a su enum, los metadatos a un array y los campos de duplicado a fecha y entero. */
    protected function casts(): array
    {
        return [
            'action' => IncidentAction::class,
            'metadata' => 'array',
            'voided_at' => 'datetime',
            'duplicate_of_id' => 'integer',
        ];
    }
}
