<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageNote extends Model
{
    protected $table = 'page_notes';

    protected $fillable = [
        'page_number',
        'narrative_summary',
        'verse_links_json',
    ];

    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
            'verse_links_json' => 'array',
        ];
    }
}
