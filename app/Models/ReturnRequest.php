<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequest extends Model
{
    use HasUuids;
    protected $fillable = ['order_id', 'user_id', 'status', 'reason', 'description', 'refund_amount', 'refund_to_wallet', 'admin_response', 'processed_at'];
    protected $casts = ['refund_amount' => 'decimal:2', 'refund_to_wallet' => 'boolean', 'processed_at' => 'datetime'];
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
