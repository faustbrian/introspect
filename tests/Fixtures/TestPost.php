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
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestPost extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'content', 'user_id'];

    protected $hidden = ['draft_content'];

    protected $appends = ['excerpt'];

    protected $casts = [
        'published_at' => 'datetime',
        'is_published' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(TestUser::class);
    }
}
