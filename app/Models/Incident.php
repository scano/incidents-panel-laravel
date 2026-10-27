<?php

namespace App\Models;

use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Incident extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'description', 'status', 'priority', 'requester_name', 'assigned_to',
    ];

    /*.s. Convierte el estado y la prioridad almacenados en sus enums correspondientes. */
    protected function casts(): array
    {
        return [
            'status' => IncidentStatus::class,
            'priority' => IncidentPriority::class,
        ];
    }

    /*.s. Define la relación con el historial, ordenado del evento más antiguo al más reciente. */
    public function logs(): HasMany
    {
        return $this->hasMany(IncidentLog::class)->orderBy('created_at')->orderBy('id');
    }

    /*.s. Define la relación con los comentarios, ordenados del más reciente al más antiguo. */
    public function comments(): HasMany
    {
        return $this->hasMany(IncidentComment::class)->orderByDesc('created_at')->orderByDesc('id');
    }
}
