<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Memory extends Model
{
    protected $fillable = ['user_id', 'type', 'content', 'file_path'];

    protected $hidden = ['embedding'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
