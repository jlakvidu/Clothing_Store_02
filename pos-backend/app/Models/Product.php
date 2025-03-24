<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = [
        'name',
        'price',
        'seller_price',
        'profit',
        'discount',
        'size',
        'color',
        'category',
        'description',
        'brand_name',
        'quantity',
        'location',
        'status',
        'added_stock_amount',
        'supplier_id',
        'admin_id'
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function grnNotes()
    {
        return $this->hasMany(GRNNote::class);
    }
}
