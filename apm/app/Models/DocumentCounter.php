<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;

class DocumentCounter extends Model
{
    use HasFactory;

    protected $fillable = [
        'division_short_name',
        'year',
        'document_type',
        'counter'
    ];

    protected $casts = [
        'year' => 'integer',
        'counter' => 'integer',
    ];

    /**
     * Document type constants
     */
    const TYPE_QUARTERLY_MATRIX = 'QM';
    const TYPE_NON_TRAVEL_MEMO = 'NT';
    const TYPE_SPECIAL_MEMO = 'SPM';
    const TYPE_SINGLE_MEMO = 'SM';
    const TYPE_CHANGE_REQUEST = 'CR';
    const TYPE_SERVICE_REQUEST = 'SR';
    const TYPE_ARF = 'ARF';

    /**
     * Get the next counter value for a division and document type
     * Uses database-level locking to prevent race conditions
     */
    public static function getNextCounter(string $divisionShortName, string $documentType, int $year = null): int
    {
        $year = $year ?? date('Y');
        
        return DB::transaction(function () use ($divisionShortName, $documentType, $year) {
            // Lock the row for update to prevent race conditions
            $counter = self::where('division_short_name', $divisionShortName)
                ->where('year', $year)
                ->where('document_type', $documentType)
                ->lockForUpdate()
                ->first();

            if (!$counter) {
                // Create new counter for this division/year/type
                $counter = self::create([
                    'division_short_name' => $divisionShortName,
                    'year' => $year,
                    'document_type' => $documentType,
                    'counter' => 0
                ]);
            }

            // Increment and save
            $counter->increment('counter');
            
            return $counter->counter;
        });
    }

    /**
     * Reset counters for a new year
     */
    public static function resetCountersForNewYear(int $year): int
    {
        return self::where('year', $year - 1)->update(['counter' => 0]);
    }

    /**
     * Get all document types
     */
    public static function getDocumentTypes(): array
    {
        return [
            self::TYPE_QUARTERLY_MATRIX => 'Quarterly Matrix',
            self::TYPE_NON_TRAVEL_MEMO => 'Non Travel Memo',
            self::TYPE_SPECIAL_MEMO => 'Special Memo',
            self::TYPE_SINGLE_MEMO => 'Single Memo',
            self::TYPE_CHANGE_REQUEST => 'Change Request',
            self::TYPE_SERVICE_REQUEST => 'Service Request',
            self::TYPE_ARF => 'ARF',
        ];
    }

    /**
     * Get counter statistics for a division
     */
    public static function getDivisionStats(string $divisionShortName, int $year = null): array
    {
        $year = $year ?? date('Y');
        
        return self::where('division_short_name', $divisionShortName)
            ->where('year', $year)
            ->get()
            ->pluck('counter', 'document_type')
            ->toArray();
    }
}