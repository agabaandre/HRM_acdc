<?php

namespace App\Services;

use App\Models\DocumentCounter;
use App\Models\Division;
use Illuminate\Database\Eloquent\Model;

class DocumentNumberService
{
    /**
     * Generate a unique document number
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
        
        // Get next counter value
        $counter = DocumentCounter::getNextCounter($divisionShortName, $documentType, $year);
        
        // Format counter with leading zeros
        $formattedCounter = str_pad($counter, 3, '0', STR_PAD_LEFT);
        
        // Generate the document number
        return "AU/CDC/{$divisionShortName}/IM/{$documentType}/{$formattedCounter}";
    }

    /**
     * Generate document number for a model
     */
    public static function generateForModel(Model $model, string $documentType): string
    {
        $divisionId = null;
        $divisionShortName = null;
        
        // Try to get division info from the model
        if (method_exists($model, 'division_id') && $model->division_id) {
            $divisionId = $model->division_id;
        } elseif (method_exists($model, 'division') && $model->division) {
            $divisionId = $model->division->id;
            $divisionShortName = $model->division->division_short_name;
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
    public static function getDocumentTypeFromModel(Model $model): string
    {
        $className = class_basename($model);
        
        return match ($className) {
            'Matrix' => DocumentCounter::TYPE_QUARTERLY_MATRIX,
            'NonTravelMemo' => DocumentCounter::TYPE_NON_TRAVEL_MEMO,
            'SpecialMemo' => DocumentCounter::TYPE_SPECIAL_MEMO,
            'Activity' => DocumentCounter::TYPE_SINGLE_MEMO, // Assuming single memo is activity
            'ServiceRequest' => DocumentCounter::TYPE_SERVICE_REQUEST,
            'RequestARF' => DocumentCounter::TYPE_ARF,
            default => 'UNKNOWN'
        };
    }

    /**
     * Generate document number for any model
     */
    public static function generateForAnyModel(Model $model): string
    {
        $documentType = self::getDocumentTypeFromModel($model);
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
        int $year = null
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
}
