<?php

namespace App\Models;

use Database\Factories\ChatMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $chat_session_id
 * @property string $role
 * @property string $content
 * @property string|null $intent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ChatSession $session
 */
#[Fillable(['chat_session_id', 'role', 'content', 'intent'])]
class ChatMessage extends Model
{
    /** @use HasFactory<ChatMessageFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ChatSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }
}
