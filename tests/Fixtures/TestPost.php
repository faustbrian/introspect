<?php declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestPost extends Model
{
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
