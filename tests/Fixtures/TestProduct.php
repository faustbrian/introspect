<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestProduct extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'price', 'sku'];

    protected $casts = [
        'price' => 'decimal:2',
        'stock_quantity' => 'integer',
    ];
}
