<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupSetting extends Model
{
    protected $fillable = [
        'monthly_archive_emails',
        'monthly_archive_enabled',
        'monthly_attachment_max_mb',
    ];

    protected $casts = [
        'monthly_archive_enabled' => 'boolean',
        'monthly_attachment_max_mb' => 'integer',
    ];

    public static function instance(): self
    {
        $row = static::query()->first();
        if ($row) {
            return $row;
        }

        return static::create([
            'monthly_archive_enabled' => true,
            'monthly_attachment_max_mb' => 20,
        ]);
    }

    /**
     * @return list<string>
     */
    public function monthlyArchiveRecipientList(): array
    {
        if (empty($this->monthly_archive_emails)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $e) => trim($e),
            preg_split('/[\s,;]+/', $this->monthly_archive_emails) ?: []
        )));
    }
}
