<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuranPageCache extends Model
{
    protected $table = 'quran_pages_cache';

    protected $primaryKey = 'page_number';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'page_number',
        'juz_number',
        'surah_names',
        'payload_json',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
            'juz_number' => 'integer',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Decode the cached JSON payload into an associative array.
     */
    public function getDecodedPayloadAttribute(): array
    {
        if (empty($this->payload_json)) {
            return [];
        }

        $decoded = json_decode($this->payload_json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
