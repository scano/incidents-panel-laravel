<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentComment extends Model
{
    protected $fillable = ['user_id', 'author_name', 'body'];

    /*.s. Relaciona el comentario con la cuenta de su autor, cuando existe una cuenta vinculada. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /*.s. Relaciona el comentario con la incidencia a la que pertenece. */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }
}
