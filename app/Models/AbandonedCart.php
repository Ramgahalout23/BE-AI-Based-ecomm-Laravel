<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbandonedCart extends Model
{
    use HasUuids;
    protected $fillable = ['user_id', 'session_id', 'cart_data', 'last_active_at', 'reminder_sent'];
    protected $casts = ['reminder_sent' => 'boolean', 'last_active_at' => 'datetime'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
