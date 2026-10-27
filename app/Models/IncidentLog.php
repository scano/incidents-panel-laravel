<?php

namespace App\Models;

use App\Enums\IncidentAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'old_value', 'new_value', 'user_name', 'metadata'];

    protected function casts(): array
    {
        return ['action' => IncidentAction::class, 'metadata' => 'array', 'voided_at' => 'datetime', 'duplicate_of_id' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }
}
