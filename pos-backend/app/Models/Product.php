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
        'admin_id',
        'bar_code'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'seller_price' => 'decimal:2',
        'profit' => 'decimal:2',
        'discount' => 'decimal:2',
        'quantity' => 'integer',
        'added_stock_amount' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'bar_code' => 'string'
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

    public function image()
    {
        return $this->hasOne(ProductImages::class);
    }
}