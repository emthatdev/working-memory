<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Chat extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['user_id', 'role', 'message'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
