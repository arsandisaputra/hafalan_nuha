<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class PageProgress extends Model
{
    public const STATUS_BELUM = 'belum';
    public const STATUS_PROSES = 'proses';
    public const STATUS_HAFAL = 'hafal';

    protected $table = 'page_progress';

    protected $fillable = [
        'page_number',
        'status',
        'review_count',
        'last_reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
            'review_count' => 'integer',
            'last_reviewed_at' => 'datetime',
        ];
    }

    /**
     * Determine whether this page needs Muraja'ah (not reviewed in > 7 days).
     */
    public function needsMurajaah(): bool
    {
        if ($this->status === self::STATUS_BELUM) {
            return false;
        }

        if ($this->last_reviewed_at === null) {
            return true;
        }

        return $this->last_reviewed_at->lt(Carbon::now()->subDays(7));
    }

    /**
     * Human-readable Indonesian status label.
     */
    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_HAFAL => 'Hafal',
            self::STATUS_PROSES => 'Proses',
            default => 'Belum',
        };
    }
}
