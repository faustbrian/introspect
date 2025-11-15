<?php declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class TestProduct extends Model
{
    protected $fillable = ['name', 'price', 'sku'];
    protected $casts = [
        'price' => 'decimal:2',
        'stock_quantity' => 'integer',
    ];
}
