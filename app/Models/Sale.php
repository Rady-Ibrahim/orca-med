<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    protected $fillable = [
        'product_id',
        'pharmacy_id',
        'supplier_id',
        'province_id',
        'warehouse_id',
        'upload_batch_id',
        'quantity',
        'sold_at',
        'import_hash',
        'unit_price',
        'discount',
    ];

    protected function casts(): array
    {
        return [
            'sold_at' => 'date',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function uploadBatch(): BelongsTo
    {
        return $this->belongsTo(UploadBatch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** Revenue = quantity × unit_price × (1 − discount%). */
    public function lineRevenue(): float
    {
        return (float) $this->quantity
            * (float) ($this->unit_price ?? 0)
            * (1 - (float) ($this->discount ?? 0) / 100);
    }

    public static function revenueSql(string $table = 'sales'): string
    {
        return "{$table}.quantity * {$table}.unit_price * (1 - COALESCE({$table}.discount, 0) / 100)";
    }
}
