<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Memory extends Model
{
    protected $fillable = ['user_id', 'type', 'content', 'file_path'];

    protected $hidden = ['embedding'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function semanticSearch(int $userId, array $embedding, int $limit = 5, float $threshold = 0.6): array
    {
        $vectorLiteral = json_encode(array_map('floatval', $embedding));

        return DB::select(
            "SELECT id, user_id, type, content, file_path, created_at, updated_at,
                    1 - (embedding <=> '{$vectorLiteral}'::vector) AS similarity
             FROM memories
             WHERE user_id = ?
               AND 1 - (embedding <=> '{$vectorLiteral}'::vector) > ?
             ORDER BY embedding <=> '{$vectorLiteral}'::vector
             LIMIT ?",
            [$userId, $threshold, $limit]
        );
    }
}
