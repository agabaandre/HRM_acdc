<?php

namespace App\Services;

use App\Models\DocumentCounter;
use App\Models\Division;
use Illuminate\Database\Eloquent\Model;

class DocumentNumberService
{
    /**
     * Generate a unique document number with conflict resolution
     */
    public static function generateDocumentNumber(
        string $documentType,
        string $divisionShortName = null,
        int $divisionId = null,
        int $year = null
    ): string {
        $year = $year ?? date('Y');
        
        // Get division short name if not provided
        if (!$divisionShortName && $divisionId) {
            $division = Division::find($divisionId);
            $divisionShortName = $division ? $division->division_short_name : 'UNKNOWN';
        }
        
        // Fallback if still no short name
        if (!$divisionShortName) {
            $divisionShortName = 'UNKNOWN';
        }
        
        // Try to generate a unique document number with retry logic
        $maxRetries = 10;
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            try {
                // Get next counter value
                $counter = DocumentCounter::getNextCounter($divisionShortName, $documentType, $year);
                
                // Format counter with leading zeros
                $formattedCounter = str_pad($counter, 3, '0', STR_PAD_LEFT);
                
                // Generate the document number
                $documentNumber = "AU/CDC/{$divisionShortName}/IM/{$documentType}/{$formattedCounter}";
                
                // Check if this document number already exists in any table
                if (self::isDocumentNumberUnique($documentNumber)) {
                    return $documentNumber;
                }
                
                // If not unique, increment counter and try again
                $attempt++;
                
            } catch (\Exception $e) {
                // Log the error and retry
                \Log::warning("Document number generation attempt {$attempt} failed", [
                    'division_short_name' => $divisionShortName,
                    'document_type' => $documentType,
                    'year' => $year,
                    'error' => $e->getMessage()
                ]);
                
                $attempt++;
                
                if ($attempt >= $maxRetries) {
                    throw new \Exception("Failed to generate unique document number after {$maxRetries} attempts: " . $e->getMessage());
                }
            }
        }
        
        throw new \Exception("Failed to generate unique document number after {$maxRetries} attempts");
    }

    /**
     * Generate document number for a model
     */
    public static function generateForModel(Model $model, string $documentType): string
    {
        $divisionId = null;
        $divisionShortName = null;
        
        // Try to get division info from the model
        if (isset($model->division_id) && $model->division_id) {
            $divisionId = $model->division_id;
        } elseif (isset($model->division) && $model->division) {
            $divisionId = $model->division->id;
            $divisionShortName = $model->division->division_short_name;
        }
        
        // For Activities, try to get division through Matrix relationship
        if (class_basename($model) === 'Activity' && isset($model->matrix_id) && $model->matrix_id) {
            $matrix = $model->matrix ?? \App\Models\Matrix::find($model->matrix_id);
            if ($matrix && $matrix->division_id) {
                $divisionId = $matrix->division_id;
                $divisionShortName = $matrix->division ? $matrix->division->division_short_name : null;
            }
        }
        
        // If we have division_id but no short name, load the division
        if ($divisionId && !$divisionShortName) {
            $division = Division::find($divisionId);
            $divisionShortName = $division ? $division->division_short_name : null;
        }
        
        return self::generateDocumentNumber($documentType, $divisionShortName, $divisionId);
    }

    /**
     * Get document type from model class
     */
    public static function getDocumentTypeFromModel(Model $model): ?string
    {
        $className = class_basename($model);
        
        return match ($className) {
            'Matrix' => null, // Matrix is just a container, not a document
            'NonTravelMemo' => DocumentCounter::TYPE_NON_TRAVEL_MEMO,
            'SpecialMemo' => DocumentCounter::TYPE_SPECIAL_MEMO,
            'Activity' => self::getActivityDocumentType($model),
            'ChangeRequest' => DocumentCounter::TYPE_CHANGE_REQUEST,
            'ServiceRequest' => DocumentCounter::TYPE_SERVICE_REQUEST,
            'RequestARF' => DocumentCounter::TYPE_ARF,
            default => 'UNKNOWN'
        };
    }

    /**
     * Determine document type for Activity based on is_single_memo field
     */
    private static function getActivityDocumentType(Model $activity): string
    {
        // Check if activity is marked as single memo
        if (isset($activity->is_single_memo) && $activity->is_single_memo == 1) {
            return DocumentCounter::TYPE_SINGLE_MEMO; // SM
        }
        
        // Activities not marked as single memo are part of quarterly matrix
        return DocumentCounter::TYPE_QUARTERLY_MATRIX; // QM
    }

    /**
     * Generate document number for any model
     */
    public static function generateForAnyModel(Model $model): string
    {
        $documentType = self::getDocumentTypeFromModel($model);
        
        // If document type is null (e.g., Matrix), return empty string
        if ($documentType === null) {
            return '';
        }
        
        return self::generateForModel($model, $documentType);
    }

    /**
     * Validate document number format
     */
    public static function validateDocumentNumber(string $documentNumber): bool
    {
        $pattern = '/^AU\/CDC\/[A-Z0-9]+\/IM\/(QM|NT|SPM|SM|CR|SR|ARF)\/\d{3}$/';
        return preg_match($pattern, $documentNumber) === 1;
    }

    /**
     * Parse document number to extract components
     */
    public static function parseDocumentNumber(string $documentNumber): array
    {
        if (!self::validateDocumentNumber($documentNumber)) {
            return [];
        }
        
        $parts = explode('/', $documentNumber);
        
        return [
            'prefix' => $parts[0] . '/' . $parts[1], // AU/CDC
            'division_short_name' => $parts[2],
            'im' => $parts[3], // IM
            'document_type' => $parts[4],
            'counter' => (int) $parts[5],
            'year' => null // Would need to be stored separately or in the number
        ];
    }

    /**
     * Get next available number for preview
     */
    public static function getNextNumberPreview(
        string $documentType,
        $division = null,
        ?int $year = null
    ): string {
        $year = $year ?? date('Y');
        
        $divisionShortName = null;
        
        if (is_object($division)) {
            $divisionShortName = $division->division_short_name ?? null;
        } elseif (is_string($division)) {
            $divisionShortName = $division;
        } elseif (is_numeric($division)) {
            $division = Division::find($division);
            $divisionShortName = $division ? $division->division_short_name : null;
        }
        
        if (!$divisionShortName) {
            $divisionShortName = 'UNKNOWN';
        }
        
        // Get current counter without incrementing
        $currentCounter = DocumentCounter::where('division_short_name', $divisionShortName)
            ->where('year', $year)
            ->where('document_type', $documentType)
            ->value('counter') ?? 0;
        
        $nextCounter = $currentCounter + 1;
        $formattedCounter = str_pad($nextCounter, 3, '0', STR_PAD_LEFT);
        
        return "AU/CDC/{$divisionShortName}/IM/{$documentType}/{$formattedCounter}";
    }

    /**
     * Check if a document number is unique across all tables
     */
    private static function isDocumentNumberUnique(string $documentNumber): bool
    {
        $tables = [
            'matrices',
            'activities', 
            'non_travel_memos',
            'special_memos',
            'change_request',
            'service_requests',
            'request_arfs'
        ];

        foreach ($tables as $table) {
            $exists = \DB::table($table)
                ->where('document_number', $documentNumber)
                ->exists();
            
            if ($exists) {
                return false;
            }
        }

        return true;
    }

    /**
     * Find the next available document number for a division and document type
     */
    public static function findNextAvailableNumber(
        string $documentType,
        string $divisionShortName,
        int $year = null
    ): string {
        $year = $year ?? date('Y');
        
        // Start from counter 1
        $counter = 1;
        $maxAttempts = 1000; // Prevent infinite loop
        $attempt = 0;
        
        while ($attempt < $maxAttempts) {
            $formattedCounter = str_pad($counter, 3, '0', STR_PAD_LEFT);
            $documentNumber = "AU/CDC/{$divisionShortName}/IM/{$documentType}/{$formattedCounter}";
            
            if (self::isDocumentNumberUnique($documentNumber)) {
                return $documentNumber;
            }
            
            $counter++;
            $attempt++;
        }
        
        throw new \Exception("Could not find available document number after {$maxAttempts} attempts");
    }

    /**
     * Reset document counter to the next available number after deletions
     */
    public static function resetCounterAfterDeletion(
        string $documentType,
        string $divisionShortName,
        int $year = null
    ): void {
        $year = $year ?? date('Y');
        
        // Find the highest document number currently in use
        $highestCounter = 0;
        $tables = [
            'matrices',
            'activities', 
            'non_travel_memos',
            'special_memos',
            'change_request',
            'service_requests',
            'request_arfs'
        ];

        foreach ($tables as $table) {
            $result = \DB::table($table)
                ->where('document_number', 'LIKE', "AU/CDC/{$divisionShortName}/IM/{$documentType}/%")
                ->selectRaw('MAX(CAST(SUBSTRING_INDEX(document_number, "/", -1) AS UNSIGNED)) as max_counter')
                ->value('max_counter');
            
            if ($result && $result > $highestCounter) {
                $highestCounter = $result;
            }
        }

        // Update the counter to be one more than the highest used number
        $counter = DocumentCounter::where('division_short_name', $divisionShortName)
            ->where('year', $year)
            ->where('document_type', $documentType)
            ->first();

        if ($counter) {
            $counter->update(['counter' => $highestCounter]);
        } else {
            DocumentCounter::create([
                'division_short_name' => $divisionShortName,
                'year' => $year,
                'document_type' => $documentType,
                'counter' => $highestCounter
            ]);
        }
    }
}
