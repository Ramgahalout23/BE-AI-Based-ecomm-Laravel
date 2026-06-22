<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    use HasUuids;
    protected $fillable = ['product_id', 'name', 'sku', 'attributes', 'price', 'quantity', 'images'];
    protected $casts = ['attributes' => 'json', 'images' => 'json', 'price' => 'decimal:2'];
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
