<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocArticle extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocCategory::class, 'doc_category_id');
    }

    public function docCategory(): BelongsTo
    {
        return $this->belongsTo(DocCategory::class, 'doc_category_id');
    }
}
