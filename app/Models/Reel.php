<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Reel extends Model
{
    use HasUuids;

    protected $fillable = [
        'title',
        'description',
        'video_url',
        'image_url',
        'link_url',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    /**
     * Products associated with this reel (for shoppable ads).
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'reel_product')
                    ->withPivot('display_order')
                    ->withTimestamps()
                    ->orderBy('reel_product.display_order');
    }
}
